<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient as SWStoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\StoreLicenseViolationStruct;
use Shopware\Core\Framework\Store\Struct\StoreUpdateStruct;

/**
 * @internal
 *
 * Muckidrive extensions are not distributed via the Shopware Store. The extension listing and the
 * store API endpoints must therefore not send the installed extensions to api.shopware.com
 * (plugin updates, licenses, license violations). These lookups return empty results instead.
 */
#[Package('framework')]
final class StoreClient extends SWStoreClient
{
    /**
     * @return StoreUpdateStruct[]
     */
    public function getExtensionUpdateList(ExtensionCollection $extensionCollection, Context $context): array
    {
        return [];
    }

    /**
     * @param array<string, array{name: string, version: ?string, active: bool}> $extensions
     *
     * @return StoreLicenseViolationStruct[]
     */
    public function getLicenseViolations(Context $context, array $extensions, string $hostName): array
    {
        return [];
    }

    public function listMyExtensions(ExtensionCollection $extensions, Context $context): ExtensionCollection
    {
        return new ExtensionCollection();
    }
}
