<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\App\Payload;

use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper as ShopwareAppPayloadServiceHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * Extends the Shopware base class so this type satisfies type checks in
 * AppActionLoader and other services that expect the Shopware parent class.
 */
#[Package('framework')]
class AppPayloadServiceHelper extends ShopwareAppPayloadServiceHelper
{
}
