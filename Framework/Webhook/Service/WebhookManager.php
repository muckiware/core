<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\Webhook\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;

use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookManager implements ResetInterface
{
    /**
     * @var array<string, list<Webhook>>
     */
    private ?array $webhooks = null;

    /**
     * @var array<string, mixed>
     */
    private array $privileges = [];

    public function __construct(
        private readonly WebhookLoader $webhookLoader
    ) {
    }

    public function dispatch(object $event): void
    {
        //Old app dispatch
    }

    public function reset(): void
    {
        $this->webhooks = null;
        $this->privileges = [];
    }

    public function clearInternalWebhookCache(): void
    {
        $this->webhooks = null;
    }

    public function clearInternalPrivilegesCache(): void
    {
        $this->privileges = [];
    }

    /**
     * @return list<Webhook>
     */
    private function getWebhooks(string $eventName): array
    {
        $this->loadWebhooks();

        return $this->webhooks[$eventName] ?? [];
    }

    private function loadWebhooks(): void
    {
        if ($this->webhooks !== null) {
            return;
        }

        $webhooks = $this->webhookLoader->getWebhooks();
        foreach ($webhooks as $webhook) {
            $this->webhooks[$webhook->eventName][] = $webhook;
        }
    }
}
