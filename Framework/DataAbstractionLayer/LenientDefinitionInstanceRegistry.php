<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * Replaces the default DefinitionInstanceRegistry to avoid DefinitionNotFoundException
 * when entity definitions from disabled bundles (Checkout, SEO, etc.) are referenced
 * as association targets by registered entity definitions.
 *
 * Strategy:
 * - If the class exists in vendor but is not registered as a DI service: instantiate it
 *   directly (no-arg constructor, then compile). This preserves real field definitions
 *   so ManyToMany mapping lookups (getByStorageName) still work.
 * - If the class does not exist at all: return a GhostEntityDefinition with empty fields.
 *
 * Stores a pre-registration copy before compile() to break circular association chains.
 */
class LenientDefinitionInstanceRegistry extends DefinitionInstanceRegistry
{
    /** @var array<string, EntityDefinition> */
    private array $instances = [];

    public function get(string $class): EntityDefinition
    {
        try {
            return parent::get($class);
        } catch (\Throwable) {
            return $this->instantiateOrGhost($class);
        }
    }

    /**
     * Since Shopware 6.7.15 FkFields reference their target by entity name (e.g. "plugin") instead of
     * the definition class. get() never throws, so the parent's entity name fallback is resolved here.
     */
    public function getByClassOrEntityName(string $key): EntityDefinition
    {
        if (str_contains($key, '\\')) {
            return $this->get($key);
        }

        try {
            return $this->getByEntityName($key);
        } catch (\Throwable) {
            return $this->instances[$key] ??= $this->ghost($key);
        }
    }

    private function instantiateOrGhost(string $class): EntityDefinition
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        if (class_exists($class) && is_subclass_of($class, EntityDefinition::class)) {
            $instance = new $class();
            // Store BEFORE compile() to break circular association chains
            $this->instances[$class] = $instance;
            $instance->compile($this);

            return $instance;
        }

        return $this->instances[$class] ??= $this->ghost($class);
    }

    private function ghost(string $class): GhostEntityDefinition
    {
        $ghost = new GhostEntityDefinition($this->classToEntityName($class));
        $ghost->compile($this);

        return $ghost;
    }

    /**
     * Derives a snake_case entity name from a fully-qualified class name.
     * Entity names (no namespace separator) are returned unchanged.
     *
     * e.g. Shopware\Core\Checkout\Payment\PaymentMethodDefinition → payment_method
     */
    private function classToEntityName(string $class): string
    {
        if (!str_contains($class, '\\')) {
            return $class;
        }

        $shortName = substr($class, (int) strrpos($class, '\\') + 1);
        $shortName = (string) preg_replace('/Definition$/', '', $shortName);

        return strtolower((string) preg_replace('/([A-Z])/', '_$1', lcfirst($shortName)));
    }
}
