<?php declare(strict_types=1);

namespace Muckiware\Core\Content;

use Shopware\Core\Content\Mail\MailerConfigurationCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Content as SWContent;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Muckiware\Core\Helper;

/**
 * @internal
 */
#[Package('framework')]
class Content extends Bundle
{
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

        $loader = new XmlFileLoader($container, $configLocator);
        $loader->load('cookie.xml');
        $loader->load('media.xml');
        $loader->load('media_path.xml');
        $loader->load('property.xml');
        // cms.xml not loaded from SW Core: depends on sales_channel.product.repository (no Checkout bundle)
        // mail_template.xml not loaded from SW Core: MailAttachmentsBuilder depends on DocumentGenerator (no Checkout bundle)
        // sitemap.xml not loaded: depends on CartRuleLoader and SalesChannelContextFactory (no Checkout bundle)
        // flow.xml not loaded from SW Core: FlowRuleScopeBuilder/FlowExecutor depend on Checkout services

        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('media_test.xml');
        }
    }

    /**
     * @throws \Exception
     */
    public function loadMuWaServices(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('category.xml');
        $loader->load('cms.xml');
        $loader->load('flow.xml');
        $loader->load('mail_template.xml');
        $loader->load('rule.xml');
    }
}
