<?php declare(strict_types=1);

namespace Muckiware\Core\System;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityRegistrar;
use Shopware\Core\System\DependencyInjection\CompilerPass\NumberRangeIncrementerCompilerPass;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Shopware\Core\System\System as SWSystem;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Muckiware\Core\Helper;

/**
 * @internal
 */
#[Package('framework')]
class System extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

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

        $configLocator = new FileLocator(__DIR__ . '/DependencyInjection/');

        $phpLoader = new PhpFileLoader($container, $configLocator);
        $phpLoader->load('consent.php');

        $container->addCompilerPass(new SalesChannelEntityCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new NumberRangeIncrementerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function loadSWServices(ContainerBuilder $container): void
    {
        $systemDir = Helper::getFolderByClassName(SWSystem::class, 'system');
        $configLocator = new FileLocator($systemDir.'/DependencyInjection/');

        $loader = new PhpFileLoader($container, $configLocator);
        $loader->load('configuration.php');
        $loader->load('country.php');
        $loader->load('currency.php');
        $loader->load('custom_entity.php');
        $loader->load('locale.php');
        $loader->load('integration.php');
        $loader->load('tag.php');
    }

    /**
     * @throws \Exception
     */
    public function loadMuWaServices(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('configuration.xml');
        $loader->load('sales_channel.xml');
        $loader->load('snippet.xml');
        $loader->load('tax_provider.xml');
        $loader->load('user.xml');
    }

    public function boot(): void
    {
        parent::boot();

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        $this->container->get(CustomEntityRegistrar::class)->register();
    }
}
