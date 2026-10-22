<?php declare(strict_types=1);

namespace Muckiware\Core\System\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * muckidrive is a platform without shop context: currency management is not
 * part of the platform. The currency core services (CurrencyDefinition,
 * currency.repository, formatters, validators) are already gone — this pass
 * also strips the remaining SalesChannelCurrency aggregate (join-table
 * accessors between sales channels and currencies) that the sales_channel
 * service loader registers by default. Nothing in the loaded stack injects
 * these services; the sales_channel_currency database table itself stays in
 * place (created by the core migrations).
 */
class RemoveCurrencyServicesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() + $container->getAliases() as $id => $definition) {
            if (!is_string($id) || stripos($id, 'Currency') === false) {
                continue;
            }

            if ($container->hasDefinition($id)) {
                $container->removeDefinition($id);
            } elseif ($container->hasAlias($id)) {
                $container->removeAlias($id);
            }

            if ($definition instanceof Alias) {
                try {
                    $container->removeAlias($id);
                } catch (\Throwable) {
                    // alias already gone
                }
            }
        }
    }
}
