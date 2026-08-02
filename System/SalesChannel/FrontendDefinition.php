<?php declare(strict_types=1);

namespace Muckiware\Core\System\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class FrontendDefinition extends SalesChannelDefinition
{
    public function getEntityName(): string
    {
        return 'frontend';
    }
}
