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

/**
 * Mirrors Shopware's Framework/Resources/config/routes_overwrite.php.
 *
 * Loaded after the generic DAL API routes, so the dedicated controller routes
 * (e.g. "api.user.update", "api.user.create") replace the generic entity routes
 * of the same name. Without this, user writes hit ApiController in CRUD scope
 * and fail the WriteProtection of the "user" entity.
 */
return function (RoutingConfigurator $routes): void {
    $frameworkDir = Helper::getFolderByClassName(Framework::class, 'framework');

    $routes->import($frameworkDir . '/Api/Controller/UserController.php', 'attribute');
    $routes->import($frameworkDir . '/Api/Controller/IntegrationController.php', 'attribute');
};
