<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\InstallerException;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;

/**
 * Replaces the Shopware ShopConfigurationService: only the basic information and the default language
 * are configured. No sales channel, currency, country, payment or shipping method is set up.
 *
 * @internal
 *
 * @phpstan-type System array{
 *     name: string,
 *     locale: string,
 *     email: string,
 *     host: string,
 *     basePath: string,
 *     schema: string,
 *     blueGreenDeployment: bool
 * }
 */
#[Package('framework')]
final readonly class SystemConfigurationService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock
    ) {
    }

    /**
     * @param System $system
     */
    public function updateSystem(array $system, Connection $connection): void
    {
        if ($system['locale'] === '') {
            throw InstallerException::shopConfigurationRequiredValueMissing('locale');
        }

        $connection->transactional(function (Connection $connection) use ($system): void {
            $shopConfigurator = new ShopConfigurator($connection, $this->eventDispatcher, $this->clock);
            $shopConfigurator->updateBasicInformation($system['name'], $system['email']);
            $shopConfigurator->setDefaultLanguage($system['locale']);
        });
    }
}
