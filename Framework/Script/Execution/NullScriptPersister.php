<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\Context;

/**
 * No-op stub — Muckidrive does not use the app system, so ScriptPersister
 * needs no real implementation. Extends SW's class to satisfy the type hint
 * in Shopware\Core\Framework\Script\Execution\ScriptLoader::__construct().
 */
class NullScriptPersister extends ScriptPersister
{
    public function __construct()
    {
    }

    public function updateScripts(string $appId, Context $context): void
    {
    }

    public function activateAppScripts(string $appId, Context $context): void
    {
    }

    public function deactivateAppScripts(string $appId, Context $context): void
    {
    }

    public function refresh(): void
    {
    }
}
