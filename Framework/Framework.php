<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Framework;

use Shopware\Core\Framework\Adapter\Cache\CacheCompilerPass;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCompilerPass;
use Shopware\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionsCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Muckiware\Core\Framework\DependencyInjection\CompilerPass\LenientDefinitionRegistryPass;
use Muckiware\Core\Framework\DependencyInjection\CompilerPass\RemoveCheckoutDependentServicesPass;
use Muckiware\Core\Framework\DependencyInjection\ServiceOverrides;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AssetBundleRegistrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AssetRegistrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\CreateGeneratorScaffoldingCommandPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DisableTwigCacheWarmerCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FrameworkMigrationReplacementCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\HttpCacheConfigCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\MessengerMiddlewareCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\OverwriteSessionFactoryCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RateLimiterCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RedisPrefixCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Shopware\Core\Framework\DependencyInjection\FrameworkExtension;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Framework as SWFramework;
use Shopware\Core\Framework\Increment\IncrementerGatewayCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageHandlerCompilerPass;
use Shopware\Core\Framework\Telemetry\Metrics\MeterProvider;
use Shopware\Core\Framework\Test\DependencyInjection\CompilerPass\ContainerVisibilityCompilerPass;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

use Muckiware\Core\Helper;

/**
 * @internal
 */
#[Package('framework')]
class Framework extends Bundle
{
    private const PACKAGE_CONFIG_EXTS = '.{yaml,yml}';

    /**
     * Shopware services that are not supported on Muckidrive (shop, sales channel and Storefront features).
     */
    private const UNSUPPORTED_SW_SERVICES = [
        'console.command.assets_install',
        'Shopware\\Core\\Checkout\\Cart\\ApiOrderCartService',
        'Shopware\\Core\\Framework\\Adapter\\Cache\\CacheStateSubscriber',
        'Shopware\\Core\\Framework\\Adapter\\Twig\\AppTemplateIterator',
        'Shopware\\Core\\Framework\\Adapter\\Twig\\Extension\\InAppPurchaseExtension',
        'Shopware\\Core\\Framework\\Adapter\\Twig\\Filter\\CurrencyFilter',
        'Shopware\\Core\\Framework\\Api\\Controller\\SalesChannelProxyController',
        'Shopware\\Core\\Framework\\App\\Lifecycle\\Update\\AbstractAppUpdater',
        'Shopware\\Core\\Framework\\App\\ScheduledTask\\UpdateAppsHandler',
        'Shopware\\Core\\Framework\\App\\ScheduledTask\\UpdateAppsTask',
        'Shopware\\Core\\Framework\\Demodata\\PersonalData\\CleanPersonalDataCommand',
        'Shopware\\Core\\Framework\\Routing\\SalesChannelRequestContextResolver',
        'Shopware\\Core\\Framework\\Webhook\\Service\\WebhookManager',
        'Shopware\\Core\\Framework\\Webhook\\WebhookCacheClearer',
        'Shopware\\Core\\Framework\\Webhook\\WebhookDispatcher',
        'Shopware\\Core\\System\\CustomEntity\\CustomEntityLifecycleService',
    ];

    /**
     * Shopware services whose class is replaced by a Muckiware subclass. The vendor arguments are kept.
     */
    private const SW_CLASS_REPLACEMENTS = [
        'Shopware\\Core\\Framework\\App\\Hmac\\QuerySigner' => \Muckiware\Core\Framework\App\Hmac\QuerySigner::class,
        'Shopware\\Core\\Framework\\App\\Payload\\AppPayloadServiceHelper' => \Muckiware\Core\Framework\App\Payload\AppPayloadServiceHelper::class,
    ];

    /**
     * Shopware services that are fetched directly from the container by Muckiware code.
     */
    private const PUBLIC_SW_SERVICES = [
        'shopware.asset.asset',
        'Shopware\\Core\\Framework\\Api\\OAuth\\SymfonyBearerTokenValidator',
    ];

    public function getTemplatePriority(): int
    {
        return -1;
    }

    public function getContainerExtension(): Extension
    {
        return new FrameworkExtension();
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function build(ContainerBuilder $container): void
    {
        $container->setParameter('locale', 'en-GB');
        $this->loadSWServices($container);
        $this->loadMuWaServices($container);
        $this->applyServiceOverrides($container);

        /** Needs to run after @see RegisterAutoconfigureAttributesPass (priority 100) to include all services that are autoconfigured */
        $container->addCompilerPass(new AttributeEntityCompilerPass(new AttributeEntityCompiler()), PassConfig::TYPE_BEFORE_OPTIMIZATION, 99);
        // make sure to remove services behind a feature flag, before some other compiler passes may reference them, therefore the high priority
        $container->addCompilerPass(new FeatureFlagCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new DisableTwigCacheWarmerCompilerPass());
        $container->addCompilerPass(new DefaultTransportCompilerPass());
        $container->addCompilerPass(new MessengerMiddlewareCompilerPass());
        $container->addCompilerPass(new TwigLoaderConfigCompilerPass());
        $container->addCompilerPass(new TwigEnvironmentCompilerPass());
        $container->addCompilerPass(new RouteScopeCompilerPass());
        $container->addCompilerPass(new AssetRegistrationCompilerPass());
        $container->addCompilerPass(new AssetBundleRegistrationCompilerPass());
        $container->addCompilerPass(new FilesystemConfigMigrationCompilerPass());
        $container->addCompilerPass(new RateLimiterCompilerPass());
        $container->addCompilerPass(new IncrementerGatewayCompilerPass());
        $container->addCompilerPass(new ReverseProxyCompilerPass());
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new OverwriteSessionFactoryCompilerPass());
        $container->addCompilerPass(new RedisPrefixCompilerPass(), PassConfig::TYPE_BEFORE_REMOVING, 0);
        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new HttpCacheConfigCompilerPass());
        $container->addCompilerPass(new MessageHandlerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new CreateGeneratorScaffoldingCommandPass());
        $container->addCompilerPass(new RedisConnectionsCompilerPass());

        if ($container->getParameter('kernel.environment') === 'test') {
            $container->addCompilerPass(new DisableRateLimiterCompilerPass());
            $container->addCompilerPass(new ContainerVisibilityCompilerPass());
        }

        $container->addCompilerPass(new FrameworkMigrationReplacementCompilerPass());
        // Replaces DefinitionInstanceRegistry class so missing entity definitions (Checkout etc.)
        // resolve to ghost stubs instead of throwing DefinitionNotFoundException.
        // Must run before EntityCompilerPass (priority 0 at TYPE_BEFORE_OPTIMIZATION).
        $container->addCompilerPass(new LenientDefinitionRegistryPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 50);
        // Runs at TYPE_BEFORE_REMOVING (after bundle build), removes services that depend on
        // missing Checkout bundle services before Symfony's validation pass fires.
        $container->addCompilerPass(new RemoveCheckoutDependentServicesPass(), PassConfig::TYPE_BEFORE_REMOVING, 100);
        // Second run: later BEFORE_REMOVING passes re-register service locators (same hash id) with their
        // original map, which brings back closures to removed services (e.g. controller value resolvers).
        $container->addCompilerPass(new RemoveCheckoutDependentServicesPass(), PassConfig::TYPE_BEFORE_REMOVING, -250);

        parent::build($container);
        $this->buildSWDefaultConfig($container);
    }

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function loadSWServices(ContainerBuilder $container): void
    {
        $frameworkFolder = Helper::getFolderByClassName(SWFramework::class, 'framework');
        $loader = new PhpFileLoader($container, new FileLocator($frameworkFolder.'/DependencyInjection/'));

        $loader->load('acl.php');
        $loader->load('api.php');
        $loader->load('app.php');
        $loader->load('cache.php');
        $loader->load('filesystem.php');
        $loader->load('plugin.php');
        $loader->load('script.php');
        $loader->load('services.php');
        $loader->load('webhook.php');
        $loader->load('custom-field.php');
        $loader->load('data-abstraction-layer.php');
        $loader->load('event.php');
        $loader->load('hydrator.php');
        $loader->load('message-queue.php');
        $loader->load('rule.php');
        $loader->load('scheduled-task.php');
        $loader->load('language.php');
//        $loader->load('seo.php');
        $loader->load('rate-limiter.php');
        $loader->load('increment.php');
        $loader->load('flag.php');
        $loader->load('health.php');
        $loader->load('telemetry.php');
        $loader->load('notification.php');
        $loader->load('sso.php');
        $loader->load('store.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('services_test.php');
            $loader->load('store_test.php');
            $loader->load('seo_test.php');
            $loader->load('app_test.php');
        }
    }

    /**
     * @throws \Exception
     */
    public function loadMuWaServices(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('overrides.xml');
    }

    /**
     * Loads the package configuration (feature flags, shopware.yaml, ...) of the installed Shopware
     * Core framework, like Bundle::buildDefaultConfig() does for the bundle's own Resources/config.
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function buildSWDefaultConfig(ContainerBuilder $container): void
    {
        $configDir = Helper::getFolderByClassName(SWFramework::class, 'framework') . '/Resources/config';
        $locator = new FileLocator($configDir);
        $configLoader = new DelegatingLoader(new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
        ]));

        $env = $container->getParameter('kernel.environment');
        \assert(\is_string($env));

        // YAML only: packages/mcp.php configures MCP servers for the Admin and Store API tool sets,
        // which are not registered on Muckidrive.
        $configLoader->load($configDir . '/{packages}/*' . self::PACKAGE_CONFIG_EXTS, 'glob');
        $configLoader->load($configDir . '/{packages}/' . $env . '/*' . self::PACKAGE_CONFIG_EXTS, 'glob');
    }

    private function applyServiceOverrides(ContainerBuilder $container): void
    {
        ServiceOverrides::remove($container, self::UNSUPPORTED_SW_SERVICES);
        ServiceOverrides::replaceClasses($container, self::SW_CLASS_REPLACEMENTS);
        ServiceOverrides::makePublic($container, self::PUBLIC_SW_SERVICES);
    }

    public function boot(): void
    {
        parent::boot();

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        /** @var FeatureFlagRegistry $featureFlagRegistry */
        $featureFlagRegistry = $this->container->get(FeatureFlagRegistry::class);
        $featureFlagRegistry->register();

        if ($this->container->getParameter('kernel.environment') !== 'test') {
            // Inject the meter early in the application lifecycle. This is needed to use the meter in special case (static contexts).
            MeterProvider::bindMeter($this->container);
        }

        CacheValueCompressor::$compress = $this->container->getParameter('shopware.cache.compress');
        CacheValueCompressor::$compressMethod = $this->container->getParameter('shopware.cache.compression_method');
        Feature::$emitDeprecations = $this->container->getParameter('kernel.debug');

        /** @var StampedeProtectionConfigurator $stampedeProtectionConfigurator */
        $stampedeProtectionConfigurator = $this->container->get(StampedeProtectionConfigurator::class);
        $stampedeProtectionConfigurator->apply();
    }
}
