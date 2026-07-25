<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\App\Hmac;

use Shopware\Core\Framework\App\Hmac\QuerySigner as ShopwareQuerySigner;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * Extends the Shopware base class so this type satisfies type checks in
 * ModuleLoader and other services that expect the Shopware parent class.
 */
#[Package('framework')]
class QuerySigner extends ShopwareQuerySigner
{
}
