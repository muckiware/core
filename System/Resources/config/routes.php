<?php declare(strict_types=1);

use Muckiware\Core\Helper;
use Shopware\Core\System\System;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $systemDir = Helper::getFolderByClassName(System::class, 'system');

    $routes->import($systemDir . '/SystemConfig/**/*Controller.php', 'attribute');
    $routes->import($systemDir . '/User/Api/**/*Controller.php', 'attribute');
    $routes->import($systemDir . '/CustomField/**/*Controller.php', 'attribute');
    $routes->import($systemDir . '/StateMachine/Api/*Controller.php', 'attribute');
    $routes->import($systemDir . '/NumberRange/**/*Controller.php', 'attribute');
    $routes->import($systemDir . '/SalesChannel/SalesChannel/**/*Controller.php', 'attribute');
};
