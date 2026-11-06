<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Installer\Database\MigrationCollectionFactory as SWMigrationCollectionFactory;

/**
 * Collects the Shopware Core migrations plus the migrations of the Muckiware bundles, which are added
 * by compiler passes in the regular kernel (e.g. AdministrationMigrationCompilerPass of muckiware/master).
 * Storefront and Shopware Administration migrations are not part of Muckidrive.
 *
 * Extends the Shopware class because DatabaseMigrator type-hints it.
 *
 * @internal
 */
#[Package('framework')]
final class MigrationCollectionFactory extends SWMigrationCollectionFactory
{
    private const MAJOR_VERSIONS = ['V6_3', 'V6_4', 'V6_5', 'V6_6', 'V6_7'];

    /**
     * Bundle class => migration namespace prefix. The migrations live in <bundle dir>/Migration/<major>.
     */
    private const BUNDLE_MIGRATIONS = [
        'Muckiware\\Master\\Administration' => 'Muckiware\\Master\\Migration',
    ];

    public function getMigrationCollectionLoader(Connection $connection): MigrationCollectionLoader
    {
        $nullLogger = new NullLogger();

        return new MigrationCollectionLoader(
            $connection,
            new MigrationRuntime($connection, $nullLogger),
            $nullLogger,
            $this->collect(),
        );
    }

    /**
     * @return list<MigrationSource>
     */
    private function collect(): array
    {
        $sources = [new MigrationSource('core', [])];

        foreach (self::MAJOR_VERSIONS as $major) {
            $sources[] = $this->createMigrationSource($major);
        }

        return $sources;
    }

    private function createMigrationSource(string $major): MigrationSource
    {
        $coreBasePath = \dirname((string) new \ReflectionClass(Defaults::class)->getFileName());

        $source = new MigrationSource('core.' . $major, [
            \sprintf('%s/Migration/%s', $coreBasePath, $major) => \sprintf('Shopware\\Core\\Migration\\%s', $major),
        ]);

        foreach (self::BUNDLE_MIGRATIONS as $bundleClass => $namespace) {
            if (!\class_exists($bundleClass)) {
                continue;
            }

            $migrationPath = \sprintf('%s/Migration/%s', \dirname((string) new \ReflectionClass($bundleClass)->getFileName()), $major);
            if (\is_dir($migrationPath)) {
                $source->addDirectory($migrationPath, $namespace . '\\' . $major);
            }
        }

        return $source;
    }
}
