<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\DependencyInjection\CompilerPass;

use Muckiware\Core\Framework\DependencyInjection\ServiceOverrides;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Removes service definitions whose REQUIRED dependencies reference non-existent services.
 *
 * Rules:
 * - Direct Reference arguments: REQUIRED — missing service causes the parent to be removed.
 * - IteratorArgument / ServiceLocatorArgument: OPTIONAL collections — missing entries filtered out.
 * - Method-call arguments (e.g. profiler->add($collector)): treat as OPTIONAL — filter stale refs.
 *
 * Runs iteratively: removing service A may make service B unresolvable.
 *
 * After all removals, cleans up env-var placeholders from the parameter bag for any env var
 * whose resolved placeholder string no longer appears in any remaining service definition.
 * This prevents PhpDumper from throwing "Environment variable X is never used."
 *
 * Runs at TYPE_BEFORE_REMOVING with high priority — after tag resolution, before Symfony validation.
 */
class RemoveCheckoutDependentServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Capture service IDs already removed by Symfony's optimization passes (e.g. inlined by
        // InlineServiceDefinitionsPass at TYPE_OPTIMIZE). These must NOT be treated as "genuinely
        // missing" — they were legitimately registered and merely optimized away. Without this
        // snapshot, method calls referencing inlined services (e.g. assets.packages::setDefaultPackage
        // referencing shopware.asset.asset) would be incorrectly stripped by cleanupOptionalReferences,
        // causing runtime errors like "There is no 'asset' asset package".
        // Services removed on purpose (ServiceOverrides::remove()) are genuinely missing, not inlined.
        $preInlinedIds = array_diff_key($container->getRemovedIds(), ServiceOverrides::pullRemovedIds($container));

        do {
            // Clean optional collection arguments (IteratorArgument, ServiceLocatorArgument, method calls)
            $this->cleanupOptionalReferences($container, $preInlinedIds);

            // Remove services with missing REQUIRED dependencies.
            $removedIds = $this->removeServicesWithMissingRequiredDeps($container, $preInlinedIds);
        } while ($removedIds !== []);

        // After all removals: clean up env-var placeholders no longer referenced by any service.
        $this->cleanupOrphanedEnvPlaceholders($container);
    }

    /**
     * Returns true when a service is genuinely absent (never registered, or is a Checkout service).
     * Returns false for services that were inlined by Symfony's optimization passes — those are
     * still "available", just merged into their sole usage site.
     *
     * @param array<string, true> $preInlinedIds snapshot from before this pass ran
     */
    private function isGenuinelyMissing(string $id, ContainerBuilder $container, array $preInlinedIds): bool
    {
        return !$container->has($id) && !isset($preInlinedIds[$id]);
    }

    /**
     * @param array<string, true> $preInlinedIds
     * @return list<string> service IDs removed
     */
    private function removeServicesWithMissingRequiredDeps(ContainerBuilder $container, array $preInlinedIds): array
    {
        $removed = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }

            foreach ($this->extractRequiredReferences($definition->getArguments()) as $referencedId) {
                if ($this->isGenuinelyMissing($referencedId, $container, $preInlinedIds)) {
                    $container->removeDefinition($id);
                    $removed[] = $id;
                    break;
                }
            }
        }

        return $removed;
    }

    /**
     * Filter stale references from optional collections and method-call arguments.
     *
     * @param array<string, true> $preInlinedIds
     */
    private function cleanupOptionalReferences(ContainerBuilder $container, array $preInlinedIds): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $definition->setArguments(
                $this->filterOptionalArguments($definition->getArguments(), $container, $preInlinedIds)
            );

            // Filter method-call arguments: remove calls that reference a genuinely missing service.
            $filteredMethodCalls = [];
            foreach ($definition->getMethodCalls() as $call) {
                $args = $call[1];
                $hasStaleRef = false;
                foreach ($this->flattenReferences($args) as $ref) {
                    if ($this->isGenuinelyMissing((string) $ref, $container, $preInlinedIds)) {
                        $hasStaleRef = true;
                        break;
                    }
                }
                if (!$hasStaleRef) {
                    $filteredMethodCalls[] = $call;
                }
            }
            $definition->setMethodCalls($filteredMethodCalls);
        }
    }

    /**
     * Removes env-var placeholder registrations for env vars that are no longer referenced
     * in any remaining service definition. Without this, Symfony's PhpDumper throws
     * "Environment variable X is never used" when a removed service was the sole consumer.
     *
     * Strategy: an env var is considered "used" if any of its placeholder strings appears in:
     * (a) remaining service argument string values, OR
     * (b) container parameter values.
     *
     * IMPORTANT: ResolveParameterPlaceHoldersPass calls $bag->resolve() as part of its process().
     * This converts %env(...)% in BOTH service arguments AND container parameters to placeholder
     * strings (format: env_HASH_TYPE_HASH). So both sources must be scanned for placeholder
     * strings — NOT for raw %env(...)% patterns, which no longer exist after resolve().
     *
     * Without scanning parameter values (b), env vars used only in container config
     * (framework.trusted_headers, kernel.runtime_mode, etc.) are incorrectly removed, causing
     * PhpDumper to compile them as static strings and breaking runtime resolution.
     */
    private function cleanupOrphanedEnvPlaceholders(ContainerBuilder $container): void
    {
        $bag = $container->getParameterBag();

        if (!$bag instanceof EnvPlaceholderParameterBag) {
            return;
        }

        $envPlaceholders = $bag->getEnvPlaceholders();

        if ($envPlaceholders === []) {
            return;
        }

        // Collect all placeholder strings from service arguments AND container parameter values.
        // Both have been resolved to placeholder strings by ResolveParameterPlaceHoldersPass->resolve().
        $allStrings = [];
        foreach ($container->getDefinitions() as $definition) {
            $this->collectArgStrings($definition->getArguments(), $allStrings);
            foreach ($definition->getMethodCalls() as $call) {
                $this->collectArgStrings($call[1], $allStrings);
            }
        }
        // Parameter values also contain placeholder strings after bag->resolve().
        $this->collectArgStrings(array_values($bag->all()), $allStrings);

        $searchText = implode("\0", $allStrings);

        // An env var is orphaned only if none of its placeholder strings appear anywhere.
        $orphanedEnvVars = [];
        foreach ($envPlaceholders as $envName => $placeholderSet) {
            $isUsed = false;
            foreach (array_keys($placeholderSet) as $placeholderString) {
                if (str_contains($searchText, $placeholderString)) {
                    $isUsed = true;
                    break;
                }
            }
            if (!$isUsed) {
                $orphanedEnvVars[] = $envName;
            }
        }

        if ($orphanedEnvVars === []) {
            return;
        }

        // Remove orphaned env var entries from the parameter bag via reflection.
        $reflection = new \ReflectionObject($bag);
        try {
            $prop = $reflection->getProperty('envPlaceholders');
            $prop->setAccessible(true);
            $current = $prop->getValue($bag);
            foreach ($orphanedEnvVars as $envName) {
                unset($current[$envName]);
            }
            $prop->setValue($bag, $current);
        } catch (\ReflectionException) {
            // Reflection failed — can't clean up.
        }
    }

    /**
     * Recursively collects all scalar (string) values from service arguments.
     *
     * @param array<mixed> $arguments
     * @param list<string> $strings (mutated in place)
     */
    private function collectArgStrings(array $arguments, array &$strings): void
    {
        foreach ($arguments as $argument) {
            if (is_string($argument)) {
                $strings[] = $argument;
            } elseif (is_array($argument)) {
                $this->collectArgStrings($argument, $strings);
            } elseif ($argument instanceof IteratorArgument || $argument instanceof ServiceLocatorArgument) {
                $this->collectArgStrings($argument->getValues(), $strings);
            }
        }
    }

    /**
     * @param array<mixed> $arguments
     * @param array<string, true> $preInlinedIds
     * @param bool $nested true for values inside an array argument (e.g. the map of a ".service_locator.*" service)
     * @return array<mixed>
     */
    private function filterOptionalArguments(array $arguments, ContainerBuilder $container, array $preInlinedIds, bool $nested = false): array
    {
        $result = [];

        foreach ($arguments as $key => $argument) {
            if ($nested && $this->isMissingServiceClosure($argument, $container, $preInlinedIds)) {
                // Service locators compiled by ServiceLocatorTagPass wrap their entries in closures.
                continue;
            }

            if ($argument instanceof IteratorArgument) {
                // Do NOT use array_values() — the IteratorArgument is keyed by tag attribute
                // (e.g. 'asset', 'sitemap', 'public' for tagged_iterator('assets.package', 'package')).
                // array_values() would replace those string keys with 0, 1, 2…, breaking
                // Packages::addPackage($name, $pkg) which uses the key as the package name.
                $filtered = array_filter(
                    $argument->getValues(),
                    fn ($v) => !($v instanceof Reference) || !$this->isGenuinelyMissing((string) $v, $container, $preInlinedIds)
                );
                $result[$key] = new IteratorArgument($filtered);
            } elseif ($argument instanceof ServiceLocatorArgument) {
                $values = $argument->getValues();
                foreach ($values as $k => $v) {
                    if ($v instanceof Reference && $this->isGenuinelyMissing((string) $v, $container, $preInlinedIds)) {
                        unset($values[$k]);
                    }
                }
                $result[$key] = new ServiceLocatorArgument($values);
            } elseif (is_array($argument)) {
                $result[$key] = $this->filterOptionalArguments($argument, $container, $preInlinedIds, true);
            } else {
                $result[$key] = $argument;
            }
        }

        return $result;
    }

    /**
     * @param array<string, true> $preInlinedIds
     */
    private function isMissingServiceClosure(mixed $argument, ContainerBuilder $container, array $preInlinedIds): bool
    {
        if (!$argument instanceof ServiceClosureArgument) {
            return false;
        }

        $reference = $argument->getValues()[0] ?? null;

        return $reference instanceof Reference
            && $this->isGenuinelyMissing((string) $reference, $container, $preInlinedIds);
    }

    /**
     * @param array<mixed> $arguments
     * @return iterable<Reference>
     */
    private function flattenReferences(array $arguments): iterable
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof Reference) {
                yield $argument;
            } elseif (is_array($argument)) {
                yield from $this->flattenReferences($argument);
            }
        }
    }

    /**
     * Yields only REQUIRED service references.
     * References inside IteratorArgument/ServiceLocatorArgument are skipped (treated as optional).
     *
     * @param array<mixed> $arguments
     * @return iterable<string>
     */
    private function extractRequiredReferences(array $arguments): iterable
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof Reference) {
                yield (string) $argument;
            } elseif ($argument instanceof IteratorArgument || $argument instanceof ServiceLocatorArgument) {
                // Optional collections — skip.
            } elseif (is_array($argument)) {
                yield from $this->extractRequiredReferences($argument);
            }
        }
    }
}
