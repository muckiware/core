<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */

use Muckiware\Core\Helper;
use Shopware\Core\Framework\Framework;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {

    $frameworkDir = Helper::getFolderByClassName(Framework::class, 'framework');

    $routes->import($frameworkDir.'/Api/Controller/**/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/Plugin/**/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/Store/**/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/MessageQueue/**/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/Increment/Controller/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/Migration/**/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/App/**/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/App/**/*Route.php', 'attribute');
    $routes->import($frameworkDir.'/Script/Api/*Route.php', 'attribute');
    $routes->import($frameworkDir.'/Rule/Api/*Controller.php', 'attribute');
    $routes->import($frameworkDir.'/Sso/Controller/**/*Controller.php', 'attribute');
};
