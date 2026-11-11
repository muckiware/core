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

use Composer\InstalledVersions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\InstallerKernel as SWInstallerKernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
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
    /**
     * @param string|null $projectDir explicit project root; when null it is resolved via the Composer root package
     */
    public function __construct(string $environment, bool $debug, private readonly ?string $projectDir = null)
    {
        parent::__construct($environment, $debug);
    }

    /**
     * The Shopware implementation walks up from the kernel class file until it finds a vendor directory. That
     * resolves the wrong project when muckiware/core is symlinked from outside the project (path repository),
     * so the project dir is taken from the constructor or the Composer root package first.
     */
    public function getProjectDir(): string
    {
        if ($this->projectDir !== null && $this->projectDir !== '') {
            return rtrim($this->projectDir, '/');
        }

        return $this->resolveRootPackageDir() ?? parent::getProjectDir();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $xmlLoader->load('services.xml');

        // rebuild the container (debug mode) when the muckiware/core version changes
        $container->addResource(new FileResource(\dirname(__DIR__) . '/composer.json'));

        // Configured paths take precedence over the bundle paths, so these templates override @Installer views.
        // The original Shopware templates stay reachable via @!Installer.
        $container->loadFromExtension('twig', [
            'paths' => [__DIR__ . '/Resources/views' => 'Installer'],
            'globals' => ['muckidrive_version' => '%kernel.muckidrive_version%'],
        ]);

        // Configured translation paths are loaded after the bundle translations, so these Muckidrive-specific
        // texts override single keys; all other texts are taken from the Shopware installer.
        $container->loadFromExtension('framework', [
            'translator' => ['paths' => [__DIR__ . '/Resources/translations']],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        return [
            ...parent::getKernelParameters(),
            'kernel.muckidrive_version' => $this->resolveMuckidriveVersion(),
        ];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes);

        // Imported after the Shopware routes, so routes with the same name replace the Shopware ones.
        $routes->import(__DIR__ . '/Resources/config/routes.php');
    }

    private function resolveRootPackageDir(): ?string
    {
        $realPath = realpath(InstalledVersions::getRootPackage()['install_path']);

        return $realPath !== false && \is_dir($realPath . '/vendor') ? $realPath : null;
    }

    /**
     * Reads the version from the composer.json of muckiware/core, because vendor/composer/installed.php
     * is only refreshed by a composer install/update of the path repository.
     */
    private function resolveMuckidriveVersion(): string
    {
        $composerJson = \dirname(__DIR__) . '/composer.json';
        $composer = \is_file($composerJson)
            ? json_decode((string) file_get_contents($composerJson), true, flags: \JSON_THROW_ON_ERROR)
            : [];

        if (\is_array($composer) && \is_string($composer['version'] ?? null)) {
            return $composer['version'];
        }

        return InstalledVersions::getPrettyVersion('muckiware/core') ?? '';
    }
}
