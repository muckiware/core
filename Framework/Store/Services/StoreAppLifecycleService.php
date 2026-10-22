<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopware\Core\Framework\Store\StoreException;

/**
 * @internal
 *
 * Muckidrive has no app lifecycle (AppManager/AppLifecycle are not registered), so the extension
 * management only supports plugins. Every app operation is rejected with a store exception.
 */
#[Package('framework')]
final class StoreAppLifecycleService extends AbstractStoreAppLifecycleService
{
    private const NOT_SUPPORTED_MESSAGE = 'App extensions are not supported on Muckidrive. Only plugins can be managed.';

    public function installExtension(string $technicalName, Context $context): void
    {
        throw $this->appsNotSupported();
    }

    public function uninstallExtension(string $technicalName, Context $context, bool $keepUserData = false): void
    {
        throw $this->appsNotSupported();
    }

    public function removeExtensionAndCancelSubscription(int $licenseId, string $technicalName, string $id, bool $keepUserData, Context $context): void
    {
        throw $this->appsNotSupported();
    }

    public function deleteExtension(string $technicalName, bool $keepUserData, Context $context): void
    {
        throw $this->appsNotSupported();
    }

    public function activateExtension(string $technicalName, Context $context): void
    {
        throw $this->appsNotSupported();
    }

    public function deactivateExtension(string $technicalName, Context $context): void
    {
        throw $this->appsNotSupported();
    }

    public function updateExtension(string $technicalName, bool $allowNewPermissions, Context $context): void
    {
        throw $this->appsNotSupported();
    }

    protected function getDecorated(): AbstractStoreAppLifecycleService
    {
        throw new DecorationPatternException(self::class);
    }

    private function appsNotSupported(): StoreException
    {
        return StoreException::extensionInstallException(self::NOT_SUPPORTED_MESSAGE);
    }
}
