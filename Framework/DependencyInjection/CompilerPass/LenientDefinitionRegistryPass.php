<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\DependencyInjection\CompilerPass;

use Muckiware\Core\Framework\DataAbstractionLayer\LenientDefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Replaces the DefinitionInstanceRegistry class with LenientDefinitionInstanceRegistry
 * so that missing entity definitions (from disabled bundles like Checkout) resolve to
 * ghost stubs instead of throwing DefinitionNotFoundException.
 *
 * Must run at TYPE_BEFORE_OPTIMIZATION with high priority so the class is set before
 * EntityCompilerPass fills in the definitions and repositoryMap arguments.
 */
class LenientDefinitionRegistryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(DefinitionInstanceRegistry::class)) {
            return;
        }

        $container->getDefinition(DefinitionInstanceRegistry::class)
            ->setClass(LenientDefinitionInstanceRegistry::class);
    }
}
