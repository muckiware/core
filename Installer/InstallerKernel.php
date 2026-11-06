<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Installer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\InstallerKernel as SWInstallerKernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Web installer kernel for Muckidrive.
 *
 * Builds on the Shopware installer (bundle, services, views, translations and assets are loaded from
 * vendor/shopware/core/Installer). Only the steps that differ on Muckidrive are replaced:
 * - migrations of muckiware/master are executed as well
 * - the shop configuration only sets up the system basics (no sales channel, currency or country)
 * - the Shopware license step is skipped
 *
 * @internal
 */
#[Package('framework')]
final class InstallerKernel extends SWInstallerKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $xmlLoader->load('services.xml');

        // Configured paths take precedence over the bundle paths, so these templates override @Installer views.
        // The original Shopware templates stay reachable via @!Installer.
        $container->loadFromExtension('twig', [
            'paths' => [__DIR__ . '/Resources/views' => 'Installer'],
        ]);

        // Configured translation paths are loaded after the bundle translations, so these Muckidrive-specific
        // texts override single keys; all other texts are taken from the Shopware installer.
        $container->loadFromExtension('framework', [
            'translator' => ['paths' => [__DIR__ . '/Resources/translations']],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes);

        // Imported after the Shopware routes, so routes with the same name replace the Shopware ones.
        $routes->import(__DIR__ . '/Resources/config/routes.php');
    }
}
