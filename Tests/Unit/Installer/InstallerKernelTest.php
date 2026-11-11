<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Tests\Unit\Installer;

use Composer\InstalledVersions;
use Muckiware\Core\Installer\InstallerKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InstallerKernel::class)]
final class InstallerKernelTest extends TestCase
{
    public function testUsesExplicitProjectDir(): void
    {
        $kernel = new InstallerKernel('test', false, '/var/www/project/');

        static::assertSame('/var/www/project', $kernel->getProjectDir());
    }

    public function testFallsBackToComposerRootPackage(): void
    {
        $kernel = new InstallerKernel('test', false);

        $expected = realpath(InstalledVersions::getRootPackage()['install_path']);

        static::assertNotFalse($expected);
        static::assertSame($expected, $kernel->getProjectDir());
        static::assertDirectoryExists($kernel->getProjectDir() . '/vendor');
    }
}
