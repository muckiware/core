<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Tests\Unit\Framework\Adapter\Kernel;

use Composer\Autoload\ClassLoader;
use Muckiware\Core\Framework\Adapter\Kernel\KernelBootstrap;
use Muckiware\Core\Installer\InstallerKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(KernelBootstrap::class)]
final class KernelBootstrapTest extends TestCase
{
    private const ENV_KEYS = ['SHOPWARE_SKIP_WEBINSTALLER', 'COMPOSER_PLUGIN_LOADER'];

    private string $projectDir;

    /**
     * @var array{server: array<string, mixed>, env: array<string, mixed>}
     */
    private array $backup = ['server' => [], 'env' => []];

    /**
     * @var list<array{0: string, 1: bool, 2: ?ComposerPluginLoader}>
     */
    private array $appKernelCalls = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/muckidrive-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir);

        foreach (self::ENV_KEYS as $key) {
            $this->backup['server'][$key] = $_SERVER[$key] ?? null;
            $this->backup['env'][$key] = $_ENV[$key] ?? null;
            unset($_SERVER[$key], $_ENV[$key]);
        }

        $this->appKernelCalls = [];
        KernelBootstrap::$appKernelFactory = function (
            string $environment,
            bool $debug,
            ClassLoader $classLoader,
            ?ComposerPluginLoader $pluginLoader
        ): HttpKernelInterface {
            $this->appKernelCalls[] = [$environment, $debug, $pluginLoader];

            return new class implements HttpKernelInterface {
                public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
                {
                    return new Response();
                }
            };
        };
    }

    protected function tearDown(): void
    {
        KernelBootstrap::$appKernelFactory = null;

        foreach (self::ENV_KEYS as $key) {
            $this->restoreGlobal($_SERVER, $key, $this->backup['server'][$key]);
            $this->restoreGlobal($_ENV, $key, $this->backup['env'][$key]);
        }

        if (is_file($this->projectDir . '/install.lock')) {
            unlink($this->projectDir . '/install.lock');
        }
        rmdir($this->projectDir);
    }

    public function testRedirectsToInstallerWhenNotInstalled(): void
    {
        $result = $this->create(['REQUEST_URI' => '/admin']);

        static::assertInstanceOf(RedirectResponse::class, $result);
        static::assertSame('/installer', $result->getTargetUrl());
        static::assertSame(Response::HTTP_FOUND, $result->getStatusCode());
    }

    public function testRedirectKeepsBasePathAndSanitisedQuery(): void
    {
        $result = $this->create([
            'REQUEST_URI' => '/shop/admin?language=de-DE&foo=bar',
            'SCRIPT_NAME' => '/shop/index.php',
            'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
            'QUERY_STRING' => 'language=de-DE&foo=bar',
        ]);

        static::assertInstanceOf(RedirectResponse::class, $result);
        static::assertSame('/shop/installer?language=de-DE', $result->getTargetUrl());
    }

    public function testReturnsInstallerKernelForInstallerRequest(): void
    {
        $result = $this->create(['REQUEST_URI' => '/installer/requirements', 'APP_ENV' => 'prod']);

        static::assertInstanceOf(InstallerKernel::class, $result);
        static::assertSame($this->projectDir, $result->getProjectDir());
        static::assertSame('prod', $result->getEnvironment());
        static::assertFalse($result->isDebug());
        static::assertSame([], $this->appKernelCalls);
    }

    public function testReturnsAppKernelWhenInstalled(): void
    {
        touch($this->projectDir . '/install.lock');

        $result = $this->create(['REQUEST_URI' => '/admin', 'APP_ENV' => 'prod', 'APP_DEBUG' => '1']);

        static::assertNotInstanceOf(InstallerKernel::class, $result);
        static::assertNotInstanceOf(Response::class, $result);
        static::assertSame([['prod', true, null]], $this->appKernelCalls);
    }

    public function testSkipWebInstallerFromContextReturnsAppKernel(): void
    {
        $result = $this->create(['REQUEST_URI' => '/admin', 'SHOPWARE_SKIP_WEBINSTALLER' => '1']);

        static::assertNotInstanceOf(InstallerKernel::class, $result);
        static::assertNotInstanceOf(Response::class, $result);
        static::assertSame([['dev', true, null]], $this->appKernelCalls);
    }

    public function testSkipWebInstallerFromServerReturnsAppKernel(): void
    {
        $_SERVER['SHOPWARE_SKIP_WEBINSTALLER'] = '1';

        $result = $this->create(['REQUEST_URI' => '/installer']);

        static::assertNotInstanceOf(InstallerKernel::class, $result);
        static::assertCount(1, $this->appKernelCalls);
    }

    public function testComposerPluginLoaderIsUsedWhenEnabled(): void
    {
        touch($this->projectDir . '/install.lock');

        $this->create(['REQUEST_URI' => '/admin', 'COMPOSER_PLUGIN_LOADER' => '1']);

        static::assertCount(1, $this->appKernelCalls);
        static::assertInstanceOf(ComposerPluginLoader::class, $this->appKernelCalls[0][2]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function create(array $context): HttpKernelInterface|Response
    {
        $context += ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/var/www/html/public/index.php'];

        return KernelBootstrap::create($context, new ClassLoader(), $this->projectDir);
    }

    /**
     * @param array<string, mixed> $global
     */
    private function restoreGlobal(array &$global, string $key, mixed $value): void
    {
        if ($value === null) {
            unset($global[$key]);

            return;
        }

        $global[$key] = $value;
    }
}
