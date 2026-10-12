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

use Muckiware\Core\Framework\DependencyInjection\ServiceOverrides;
use Muckiware\Core\Helper;

/**
 * @internal
 */
#[Package('framework')]
class System extends Bundle
{
    /**
     * Shopware services that are not supported on Muckidrive (sales channel context, Store API, customer gateway).
     */
    private const UNSUPPORTED_SW_SERVICES = [
        'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Executor\\ContextGatewayCommandExecutor',
        'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Handler\\ChangeCheckoutOptionsCommandHandler',
        'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Handler\\LoginCustomerCommandHandler',
        'Shopware\\Core\\Framework\\Gateway\\Context\\Command\\Handler\\RegisterCustomerCommandHandler',
        'Shopware\\Core\\Framework\\Gateway\\Context\\SalesChannel\\ContextGatewayRoute',
        'Shopware\\Core\\System\\SalesChannel\\Api\\StoreApiResponseListener',
        'Shopware\\Core\\System\\SalesChannel\\Context\\BaseSalesChannelContextFactory',
        'Shopware\\Core\\System\\SalesChannel\\Context\\CachedBaseSalesChannelContextFactory',
        'Shopware\\Core\\System\\SalesChannel\\Context\\CachedSalesChannelContextFactory',
        'Shopware\\Core\\System\\SalesChannel\\Context\\CartRestorer',
        'Shopware\\Core\\System\\SalesChannel\\Context\\SalesChannelContextFactory',
        'Shopware\\Core\\System\\SalesChannel\\Context\\SalesChannelContextRestorer',
        'Shopware\\Core\\System\\SalesChannel\\Context\\SalesChannelContextService',
        'Shopware\\Core\\System\\SalesChannel\\SalesChannel\\ContextSwitchRoute',
        'Shopware\\Core\\System\\SalesChannel\\SalesChannel\\SalesChannelContextSwitcher',
        'Shopware\\Core\\System\\User\\Recovery\\UserRecoveryService',
    ];

    /**
     * Shopware services whose class is replaced by a Muckiware subclass. The vendor arguments are kept.
     */
    private const SW_CLASS_REPLACEMENTS = [
        'Shopware\\Core\\System\\SystemConfig\\Service\\ConfigurationService' => \Muckiware\Core\System\SystemConfig\Service\ConfigurationService::class,
    ];

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
        ServiceOverrides::remove($container, self::UNSUPPORTED_SW_SERVICES);
        ServiceOverrides::replaceClasses($container, self::SW_CLASS_REPLACEMENTS);

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
        $loader->load('consent.php');
        $loader->load('country.php');
        $loader->load('currency.php');
        $loader->load('custom_entity.php');
        $loader->load('locale.php');
        $loader->load('integration.php');
        $loader->load('sales_channel.php');
        $loader->load('snippet.php');
        $loader->load('tag.php');
        $loader->load('tax_provider.php');
        $loader->load('user.php');
    }

    /**
     * @throws \Exception
     */
    public function loadMuWaServices(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('overrides.xml');
    }

    public function boot(): void
    {
        parent::boot();

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        $this->container->get(CustomEntityRegistrar::class)->register();
    }
}
