<?php declare(strict_types=1);

namespace Muckiware\Core\Content;

use Shopware\Core\Content\Mail\MailerConfigurationCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Content as SWContent;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Muckiware\Core\Framework\DependencyInjection\ServiceOverrides;
use Muckiware\Core\Helper;

/**
 * @internal
 */
#[Package('framework')]
class Content extends Bundle
{
    /**
     * Shopware services that are not supported on Muckidrive (shop navigation, order/customer flows, documents).
     */
    private const UNSUPPORTED_SW_SERVICES = [
        // Category navigation and Store API routes
        'Shopware\\Core\\Content\\Category\\Cms\\CategoryNavigationCmsElementResolver',
        'Shopware\\Core\\Content\\Category\\SalesChannel\\CategoryListRoute',
        'Shopware\\Core\\Content\\Category\\SalesChannel\\CategoryRoute',
        'Shopware\\Core\\Content\\Category\\SalesChannel\\NavigationRoute',
        'Shopware\\Core\\Content\\Category\\SalesChannel\\TreeBuildingNavigationRoute',
        'Shopware\\Core\\Content\\Category\\Service\\CachedDefaultCategoryLevelLoader',
        'Shopware\\Core\\Content\\Category\\Service\\CategoryBreadcrumbBuilder',
        'Shopware\\Core\\Content\\Category\\Service\\CategoryUrlGenerator',
        'Shopware\\Core\\Content\\Category\\Service\\DefaultCategoryLevelLoader',
        'Shopware\\Core\\Content\\Category\\Service\\NavigationLoader',
        'Shopware\\Core\\Content\\Category\\Subscriber\\CategorySubscriber',
        'Shopware\\Core\\Content\\Cms\\DataResolver\\Element\\FormCmsElementResolver',
        // Order, customer and document flow actions
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\AddCustomerAffiliateAndCampaignCodeAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\AddCustomerTagAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\AddOrderAffiliateAndCampaignCodeAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\AddOrderTagAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\ChangeCustomerGroupAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\ChangeCustomerStatusAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\GenerateDocumentAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\GrantDownloadAccessAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\RemoveCustomerTagAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\RemoveOrderTagAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetCustomerCustomFieldAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetCustomerGroupCustomFieldAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetOrderCustomFieldAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Action\\SetOrderStateAction',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\A11yRenderedDocumentStorer',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\CustomerGroupStorer',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\CustomerRecoveryStorer',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\CustomerStorer',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\NewsletterRecipientStorer',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\OrderStorer',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\OrderTransactionStorer',
        'Shopware\\Core\\Content\\Flow\\Dispatching\\Storer\\ProductStorer',
        'Shopware\\Core\\Content\\Flow\\Rule\\FlowRuleScopeBuilder',
        // Document attachments and rule indexing (depend on Checkout)
        'Shopware\\Core\\Content\\Mail\\Service\\MailAttachmentsBuilder',
        'Shopware\\Core\\Content\\Rule\\DataAbstractionLayer\\RuleIndexerSubscriber',
    ];

    /**
     * {@inheritdoc}
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->loadSWServices($container);
        $this->loadMuWaServices($container);
        ServiceOverrides::remove($container, self::UNSUPPORTED_SW_SERVICES);

        $container->addCompilerPass(new MailerConfigurationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function loadSWServices(ContainerBuilder $container): void
    {
        $contentDir = Helper::getFolderByClassName(SWContent::class, 'content');
        $configLocator = new FileLocator($contentDir.'/DependencyInjection/');

        $loader = new PhpFileLoader($container, $configLocator);
        $loader->load('category.php');
        $loader->load('cms.php');
        $loader->load('cookie.php');
        $loader->load('flow.php');
        $loader->load('mail.php');
        $loader->load('mail_template.php');
        $loader->load('media.php');
        $loader->load('media_path.php');
        $loader->load('measurement_system.php');
        $loader->load('property.php');
        $loader->load('rule.php');
        // sitemap.php not loaded: depends on CartRuleLoader and SalesChannelContextFactory (no Checkout bundle)

        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('media_test.php');
        }
    }

    /**
     * @throws \Exception
     */
    public function loadMuWaServices(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('overrides.xml');
        // registers ProductSearchConfigDefinition + ProductSearchKeywordDefinition
        // required by LanguageDefinition associations — without it the DAL registry throws
        // DefinitionNotFoundException on the first language repository query.
        $loader->load('product-search.xml');
    }
}
