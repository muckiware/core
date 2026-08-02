<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Placeholder for entity definitions not registered in the DI container.
 *
 * Used by LenientDefinitionInstanceRegistry to prevent DefinitionNotFoundException
 * from cascading through association chains during entity-schema.json generation.
 * Returns an empty FieldCollection so associations from this ghost to other entities
 * are never traversed.
 */
final class GhostEntityDefinition extends EntityDefinition
{
    public function __construct(private readonly string $entityName)
    {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
