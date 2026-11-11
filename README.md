# muckiware/core

Selective loader for the Shopware 6.7 Core framework. `muckiware/core` turns `shopware/core` into an
application framework **without commerce**: it boots the DAL, DI container, events, message queue,
Admin API, app system, flows, media and mail — but no Checkout, Storefront, SEO, currency or sales
channel context.

It is the base layer of [Muckidrive](../../../CLAUDE.md); `muckiware/master` (the customized
administration) and all plugins in `custom/plugins/` run on top of it.

```
shopware/core ~v6.7      ← original service definitions, entities, migrations (vendor)
muckiware/core           ← this package: decides which parts of shopware/core are loaded
muckiware/master         ← administration without shop modules
custom/plugins/*         ← project specific plugins
```

- Package: `muckiware/core` (type `library`, MIT)
- Namespace: `Muckiware\Core\` (PSR-4, package root)
- PHP: 8.2 – 8.5
- Installed as composer path repository (`custom/muckiware/*`)

## Contents

| Path | Purpose |
|---|---|
| `Framework/` | Bundle `Muckiware\Core\Framework\Framework` — replaces `Shopware\Core\Framework\Framework` |
| `System/` | Bundle `Muckiware\Core\System\System` — replaces `Shopware\Core\System\System` |
| `Content/` | Bundle `Muckiware\Core\Content\Content` — replaces `Shopware\Core\Content\Content` |
| `Installer/` | Web installer kernel and the asset-only bundle `MuckidriveInstaller` |
| `Helper.php` | Resolves vendor directories of Shopware classes at runtime |

The bundles are registered in `config/bundles.php` instead of the Shopware Core bundles.
`Shopware\Core\Checkout\Checkout` and the Storefront are intentionally not registered.

## How it works

### 1. Hybrid service loading

Shopware ships its service definitions as PHP files (`vendor/shopware/core/*/DependencyInjection/*.php`).
Instead of copying them, every Muckiware bundle loads a **selected subset of the original vendor files**
and only describes its differences. This keeps constructor arguments in sync with the installed
Shopware version when composer updates `shopware/core`.

Each bundle's `build()` follows the same sequence:

```
loadSWServices()      → PhpFileLoader on the vendor DependencyInjection folder (whitelist)
loadMuWaServices()    → XmlFileLoader on the bundle's own DependencyInjection/overrides.xml
ServiceOverrides::*   → remove unsupported services, swap classes, make services public
addCompilerPass(...)  → the Shopware compiler passes of that bundle + Muckiware specific passes
```

The vendor path is resolved by `Helper::getFolderByClassName()`, which reflects on a Shopware class
(e.g. `Shopware\Core\Framework\Framework`) and walks up to the requested folder. Without a class name
it resolves the project root (or `PROJECT_ROOT` from the environment, used by the admin build scripts).

#### Loaded Shopware service files

| Bundle | Loaded vendor files | Not loaded (examples) |
|---|---|---|
| Framework | `acl`, `api`, `app`, `cache`, `custom-field`, `data-abstraction-layer`, `event`, `filesystem`, `flag`, `health`, `hydrator`, `increment`, `language`, `message-queue`, `notification`, `plugin`, `rate-limiter`, `rule`, `scheduled-task`, `script`, `services`, `sso`, `store`, `telemetry`, `webhook` (+ `*_test` files in the `test` env) | `seo` |
| System | `configuration`, `consent`, `country`, `custom_entity`, `integration`, `locale`, `sales_channel`, `snippet`, `tag`, `tax_provider`, `user` | `currency`, `salutation`, `state_machine`, `number_range`, `tax`, `unit`, `usage_data`, … |
| Content | `category`, `cms`, `cookie`, `flow`, `mail`, `mail_template`, `media`, `media_path`, `measurement_system`, `property`, `rule` (+ `media_test`) | `product`, `product_stream`, `sitemap`, `newsletter_recipient`, `import_export`, … |

`Framework::buildSWDefaultConfig()` additionally loads the vendor package configuration
(`Resources/config/packages/*.yaml` and `packages/<env>/*.yaml` — feature flags, `shopware.yaml`, …),
the same way `Bundle::buildDefaultConfig()` does for a bundle's own config. Only YAML is loaded;
`packages/mcp.php` is skipped because the Admin/Store API MCP tool sets are not registered.

### 2. Service overrides (`ServiceOverrides`)

Each bundle declares its differences as class constants and applies them via
`Framework\DependencyInjection\ServiceOverrides`:

| Constant | Effect |
|---|---|
| `UNSUPPORTED_SW_SERVICES` | `ServiceOverrides::remove()` — removes definitions/aliases of shop specific services (sales channel context factories, Store API routes, category navigation, order/customer flow actions, `CurrencyFilter`, webhook dispatcher, …). The removed ids are remembered in a temporary container parameter. |
| `SW_CLASS_REPLACEMENTS` | `ServiceOverrides::replaceClasses()` — swaps the class of a vendor service while keeping its arguments, tags and calls. The replacement **must extend** the original class, because other Shopware services type-hint it. |
| `PUBLIC_SW_SERVICES` | `ServiceOverrides::makePublic()` — services that Muckiware code fetches directly from the container. |

Current class replacements:

| Shopware service | Muckiware class |
|---|---|
| `Framework\App\Hmac\QuerySigner` | `Muckiware\Core\Framework\App\Hmac\QuerySigner` |
| `Framework\App\Payload\AppPayloadServiceHelper` | `Muckiware\Core\Framework\App\Payload\AppPayloadServiceHelper` (also used by `muckiware/master`) |
| `System\SystemConfig\Service\ConfigurationService` | `Muckiware\Core\System\SystemConfig\Service\ConfigurationService` |

#### `overrides.xml`

Standalone Muckiware services and aliases that redirect a Shopware service id to them:

| File | Services |
|---|---|
| `Framework/DependencyInjection/overrides.xml` | `AppJWTGenerateRoute`, `StoreAppLifecycleService` (no app lifecycle on Muckidrive), `CacheResponseSubscriber`, `ApiRequestContextResolver`, `WebhookEventMessageHandler` |
| `System/DependencyInjection/overrides.xml` | `FrontendDefinition` (entity `frontend`), `SalesChannelContextPersister` |
| `Content/DependencyInjection/overrides.xml` | `FlowExecutor` (no cart/order rule scope), `MailerTransportLoader` (no document attachments), `RuleValidator` |
| `Content/DependencyInjection/product-search.xml` | `ProductSearchConfigDefinition` + `ProductSearchKeywordDefinition` — required by associations of `LanguageDefinition` |

### 3. Running without the Checkout bundle

Many loaded Shopware services still reference Checkout services or entities. Two mechanisms make the
container compile and the DAL work anyway.

#### `RemoveCheckoutDependentServicesPass`

Compiler pass (`TYPE_BEFORE_REMOVING`) that cleans up dangling references iteratively:

- A service whose **required** constructor argument references a missing service is removed —
  which may in turn make other services unresolvable, so the pass loops until nothing changes.
- **Optional** references (tagged iterators, service locators, service closures in locator maps,
  method calls) are filtered instead of removing the parent service. Iterator keys are preserved
  (e.g. asset package names).
- Services that Symfony already inlined are *not* treated as missing; services removed on purpose
  via `ServiceOverrides::remove()` are (`ServiceOverrides::pullRemovedIds()`).
- Env var placeholders that are no longer used by any service or parameter are dropped, otherwise
  the `PhpDumper` fails with "Environment variable X is never used".

The pass is registered **twice** (priority `100` and `-250`), because later `BEFORE_REMOVING` passes
re-register service locators with their original map and bring back closures to removed services.

#### `LenientDefinitionInstanceRegistry`

`LenientDefinitionRegistryPass` swaps the class of Shopware's `DefinitionInstanceRegistry`.
Entity definitions of loaded bundles still have associations to Checkout entities (customer, order,
payment method, …). Instead of throwing `DefinitionNotFoundException`, the lenient registry:

1. instantiates and compiles the definition directly if the class exists in vendor but is not
   registered (keeps real fields, e.g. for many-to-many mapping lookups), or
2. returns a `GhostEntityDefinition` with an empty field collection if the class does not exist.

Instances are cached before `compile()` to break circular association chains. Entity-name lookups
(`getByClassOrEntityName()`, used by `FkField`s since Shopware 6.7.15) are resolved the same way.

#### `RemoveCurrencyServicesCompilerPass` (System)

Removes every remaining service or alias whose id contains `Currency` (e.g. the sales channel
currency association registered by `sales_channel.php`). The database tables stay untouched.

### 4. Routes

Shopware loads routes from each bundle's `Resources/config/routes.php`. The Muckiware bundles import
only the controllers that make sense without a shop:

- **Framework**: Admin API controllers, plugin, store, message queue, increment, migration, app
  system (controllers and routes), script API, rule API, SSO
- **System**: system config, user API, custom fields, state machine API, number range, sales channel
  (admin only)

### 5. Boot

- `Framework::boot()` registers the feature flags, binds the telemetry meter, configures cache
  compression and stampede protection (same as the Shopware Framework bundle).
- `System::boot()` registers custom entities (`CustomEntityRegistrar`).

### 6. Web installer (`Installer/`)

`public/index.php` delegates to `Framework\Adapter\Kernel\KernelBootstrap`, which boots
`Muckiware\Core\Installer\InstallerKernel` as long as there is no `install.lock` (and
`SHOPWARE_SKIP_WEBINSTALLER` is not set) and redirects every other request to `/installer`.
The front controller comes from the `muckiware/core` recipe in
[muckiware/recipes](https://github.com/muckiware/recipes) (recipe `0.1` for this package version
`0.1.x`), so the kernel selection itself is updated with `composer update`. The kernel extends the Shopware
installer kernel; bundle, views, translations and assets still come from vendor. Replaced parts:

| Part | Change |
|---|---|
| `Database\MigrationCollectionFactory` | Adds the migrations of `muckiware/master` (`V6_3` … `V6_7`); Storefront and Shopware Administration migrations are not collected |
| `Controller\RequirementsController` | Continues directly with the database configuration (no license step) |
| `Controller\LicenseController` | Keeps the route (the database configuration view links back to it) and leads back to the requirements step |
| `Controller\SystemConfigurationController` + `Configuration\SystemConfigurationService` | Replace the shop configuration step (`installer.configuration`): system name, email, default language and admin user — no sales channel, currency, country, payment or shipping method |
| `Resources/views/installer/*.html.twig` | Override `@Installer` templates (originals reachable via `@!Installer`); the license menu entry is filtered |
| `Resources/translations/messages.{de,en,en-US}.yaml` | Override only the Muckidrive specific translation keys |
| `Resources/config/routes.php` | Imported after the Shopware routes, so routes with the same name win |

The installer runs before the system is installed, so its styles and logos are shipped by the
asset-only bundle `MuckidriveInstaller` (registered in `config/bundles.php`):
`bin/console assets:install` copies `Installer/Resources/public` to
`public/bundles/muckidriveinstaller`. The version shown in the installer is read from this
package's `composer.json`.

## Extending

| Goal | Where |
|---|---|
| Load another Shopware service file | `loadSWServices()` of the matching bundle |
| Disable a Shopware service | `UNSUPPORTED_SW_SERVICES` of the matching bundle |
| Change the behaviour of a Shopware service | Subclass it and add it to `SW_CLASS_REPLACEMENTS` (preferred), or define a standalone service + alias in `overrides.xml` |
| Add a Muckiware service | `<Bundle>/DependencyInjection/overrides.xml` (XML, no PHP attributes) |
| Expose a Shopware controller | `<Bundle>/Resources/config/routes.php` |

A standalone copy of a Shopware class causes `TypeError`s wherever Shopware type-hints the original
class — prefer `extends` + class swap.

## Shopware updates

After `composer update shopware/core`:

```bash
bin/console lint:container       # constructor argument / type errors
bin/console database:migrate --all
bin/console plugin:refresh
bin/console dal:validate
```

Check in particular:

- new or renamed vendor `DependencyInjection/*.php` files and services that reference Checkout
- classes in `SW_CLASS_REPLACEMENTS` and `overrides.xml` against their vendor originals
- installer: vendor `RequirementsController`, `ShopConfigurationController`,
  `MigrationCollectionFactory` and `base.html.twig` against the Muckiware versions;
  `MAJOR_VERSIONS` when Shopware adds a new migration major (e.g. `V6_8`)
- the `frontend` entity is a MySQL view over `sales_channel` (`SELECT *` is frozen at creation) —
  new `sales_channel` columns require a migration in `muckiware/master` that recreates the view

## Legacy files

Some files are left over from the former XML-copy approach (before the Shopware 6.7.15 port) and are
**not loaded** by the current bundles:

- `Content/DependencyInjection/*.xml` except `overrides.xml` and `product-search.xml`
- `System/DependencyInjection/*.xml` except `overrides.xml`
- `Framework/DependencyInjection/in-app-purchase.xml`, `System/Resources/config/basicInformation.xml`
- copies of Shopware compiler passes in `Framework/DependencyInjection/CompilerPass/` and
  `System/DependencyInjection/CompilerPass/` — the bundles register the vendor passes; only
  `LenientDefinitionRegistryPass`, `RemoveCheckoutDependentServicesPass` and
  `RemoveCurrencyServicesCompilerPass` are Muckiware specific
- classes not wired in any service definition: `Framework/Api/Controller/InfoController`,
  `Framework/Sso/Controller/SsoController`, `Framework/Webhook/Service/WebhookManager`,
  `System/CustomEntity/CustomEntityLifecycleService`,
  `System/DependencyInjection/DependencyInjectionException`
