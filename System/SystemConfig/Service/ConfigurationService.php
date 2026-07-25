<?php declare(strict_types=1);

namespace Muckiware\Core\System\SystemConfig\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService as ShopwareConfigurationService;

/**
 * @internal
 *
 * Extends the Shopware base class so this type satisfies type checks in
 * SystemConfigValidator and other services that expect the Shopware parent class.
 */
#[Package('system-settings')]
class ConfigurationService extends ShopwareConfigurationService
{
}
