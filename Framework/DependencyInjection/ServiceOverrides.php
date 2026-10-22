<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Framework\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adjusts Shopware Core service definitions after they were loaded from the vendor PHP files.
 *
 * Muckidrive loads the original Shopware service definitions and only describes its differences:
 * services that are not supported (shop/Checkout specific) and services whose class is replaced
 * by a Muckiware subclass. This keeps the constructor arguments in sync with the installed
 * Shopware version.
 */
final class ServiceOverrides
{
    private const REMOVED_IDS_PARAMETER = 'muckiware.removed_sw_services';

    /**
     * @param list<string> $serviceIds
     */
    public static function remove(ContainerBuilder $container, array $serviceIds): void
    {
        foreach ($serviceIds as $serviceId) {
            $container->removeDefinition($serviceId);
            $container->removeAlias($serviceId);
        }

        $removedIds = $container->hasParameter(self::REMOVED_IDS_PARAMETER)
            ? $container->getParameter(self::REMOVED_IDS_PARAMETER)
            : [];
        $container->setParameter(self::REMOVED_IDS_PARAMETER, [...$removedIds, ...$serviceIds]);
    }

    /**
     * Returns the ids removed via remove() and drops the temporary container parameter.
     *
     * @return array<string, true>
     */
    public static function pullRemovedIds(ContainerBuilder $container): array
    {
        if (!$container->hasParameter(self::REMOVED_IDS_PARAMETER)) {
            return [];
        }

        $removedIds = $container->getParameter(self::REMOVED_IDS_PARAMETER);
        $container->getParameterBag()->remove(self::REMOVED_IDS_PARAMETER);

        return array_fill_keys($removedIds, true);
    }

    /**
     * Swaps the class of a Shopware service while keeping its vendor arguments, tags and calls.
     * The replacement class must extend the original one.
     *
     * @param array<string, class-string> $classes service id => replacement class
     */
    public static function replaceClasses(ContainerBuilder $container, array $classes): void
    {
        foreach ($classes as $serviceId => $class) {
            $container->getDefinition($serviceId)->setClass($class);
        }
    }

    /**
     * @param list<string> $serviceIds
     */
    public static function makePublic(ContainerBuilder $container, array $serviceIds): void
    {
        foreach ($serviceIds as $serviceId) {
            $container->getDefinition($serviceId)->setPublic(true);
        }
    }
}
