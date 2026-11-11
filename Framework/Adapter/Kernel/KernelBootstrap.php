<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Framework\Adapter\Kernel;

use Composer\Autoload\ClassLoader;
use Muckiware\Core\Installer\InstallerKernel;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Installer\Helper\InstallerRedirectHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Selects the kernel for the Muckidrive front controller (public/index.php).
 *
 * The selection logic lives in this package instead of the project's front controller, so fixes are
 * shipped with a regular "composer update" of muckiware/core:
 * - no install.lock (and SHOPWARE_SKIP_WEBINSTALLER not set): every request outside of /installer is
 *   redirected to the web installer, requests to /installer are handled by the Muckidrive installer kernel
 * - otherwise the regular Shopware kernel is created via KernelFactory
 *
 * The Symfony Runtime outputs a returned Response, so the redirect needs no header()/exit.
 */
final class KernelBootstrap
{
    private const INSTALLER_PATH = '/installer';

    private const DEFAULT_SCRIPT_NAME = 'index.php';

    /**
     * Test seam to replace the creation of the regular Shopware kernel (KernelFactory::create()).
     *
     * @internal
     *
     * @var (\Closure(string, bool, ClassLoader, ?ComposerPluginLoader): HttpKernelInterface)|null
     */
    public static ?\Closure $appKernelFactory = null;

    /**
     * @param array<string, mixed> $context runtime context ($_SERVER and $_ENV merged by the Symfony Runtime)
     */
    public static function create(
        array $context,
        ClassLoader $classLoader,
        string $projectDir
    ): HttpKernelInterface|Response {
        $installerRequired = self::isInstallerRequired($context, $projectDir);

        if ($installerRequired && !self::isInstallerRequest($context)) {
            return self::createInstallerRedirect($context);
        }

        $appEnv = (string) ($context['APP_ENV'] ?? 'dev');
        $debug = (bool) ($context['APP_DEBUG'] ?? ($appEnv !== 'prod'));

        if ($installerRequired) {
            return new InstallerKernel($appEnv, $debug, $projectDir);
        }

        return self::createAppKernel($context, $classLoader, $appEnv, $debug);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function isInstallerRequired(array $context, string $projectDir): bool
    {
        if (self::getVariable($context, 'SHOPWARE_SKIP_WEBINSTALLER')) {
            return false;
        }

        return !file_exists($projectDir . '/install.lock');
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function isInstallerRequest(array $context): bool
    {
        $requestUri = (string) self::getServerValue($context, 'REQUEST_URI', '');

        return str_contains($requestUri, self::INSTALLER_PATH);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function createInstallerRedirect(array $context): RedirectResponse
    {
        return new RedirectResponse(
            self::getBaseUrl($context) . self::INSTALLER_PATH . self::buildInstallerQuery($context)
        );
    }

    /**
     * Base path of the front controller, e.g. "/shop" for "/shop/index.php" or "" for "/index.php".
     *
     * @param array<string, mixed> $context
     */
    private static function getBaseUrl(array $context): string
    {
        $scriptFilename = (string) self::getServerValue($context, 'SCRIPT_FILENAME', self::DEFAULT_SCRIPT_NAME);
        $scriptName = (string) self::getServerValue($context, 'SCRIPT_NAME', '');

        return rtrim(str_replace(basename($scriptFilename), '', $scriptName), '/');
    }

    /**
     * Forwards the sanitised query (e.g. the installer language) to the installer.
     * InstallerRedirectHelper was introduced in Shopware 6.7.5.0, older releases get no query.
     *
     * @param array<string, mixed> $context
     */
    private static function buildInstallerQuery(array $context): string
    {
        if (!class_exists(InstallerRedirectHelper::class)) {
            return '';
        }

        $serverVariables = $context;
        $serverVariables['QUERY_STRING'] = self::getServerValue($context, 'QUERY_STRING', '');

        return (new InstallerRedirectHelper($serverVariables))->buildQueryString();
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function createAppKernel(
        array $context,
        ClassLoader $classLoader,
        string $appEnv,
        bool $debug
    ): HttpKernelInterface {
        $pluginLoader = self::getVariable($context, 'COMPOSER_PLUGIN_LOADER')
            ? new ComposerPluginLoader($classLoader, null)
            : null;

        if (self::$appKernelFactory !== null) {
            return (self::$appKernelFactory)($appEnv, $debug, $classLoader, $pluginLoader);
        }

        return KernelFactory::create(
            environment: $appEnv,
            debug: $debug,
            classLoader: $classLoader,
            pluginLoader: $pluginLoader,
        );
    }

    /**
     * Reads an environment variable from the runtime context, falling back to the super globals
     * (including the transformers registered at the EnvironmentHelper).
     *
     * @param array<string, mixed> $context
     */
    private static function getVariable(array $context, string $key): mixed
    {
        if (\array_key_exists($key, $context)) {
            return $context[$key];
        }

        return EnvironmentHelper::getVariable($key, false);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function getServerValue(array $context, string $key, string $default): mixed
    {
        return $context[$key] ?? $_SERVER[$key] ?? $default;
    }
}
