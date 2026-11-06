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
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Asset-only bundle, registered in the regular kernel: bin/console assets:install copies
 * Resources/public to public/bundles/muckidriveinstaller, where the web installer loads the
 * Muckidrive styles and logos from (the installer runs before the system is installed).
 *
 * @internal
 */
#[Package('framework')]
final class MuckidriveInstaller extends Bundle
{
}
