<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\Webhook\Handler;


use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('framework')]
final readonly class WebhookEventMessageHandler
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;

    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $webhookEventLogRepository
     */
    public function __construct(
        private EntityRepository $webhookEventLogRepository
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        $shopwareVersion = $message->getShopwareVersion();

        $payload = $message->getPayload();
        $url = $message->getUrl();

        $timestamp = time();
        $payload['timestamp'] = $timestamp;

        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'sw-version' => $shopwareVersion,
            ],
            $message->getWebhookHeaders()
        );

        // LanguageId and UserLocale will be required from 6.5.0 onward
        if ($message->getLanguageId() && $message->getUserLocale()) {
            $headers = array_merge($headers, [AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $message->getLanguageId(), AuthMiddleware::SHOPWARE_USER_LANGUAGE => $message->getUserLocale()]);
        }

        $requestContent = [
            'headers' => $headers,
            'body' => $jsonPayload,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::TIMEOUT,
        ];

        if ($message->getSecret()) {
            $requestContent[AuthMiddleware::APP_REQUEST_TYPE] = [
                AuthMiddleware::APP_SECRET => $message->getSecret(),
            ];
        }

        $context = Context::createDefaultContext();

        $this->updateLogIfItExists(
            [
                'id' => $message->getWebhookEventId(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_RUNNING,
                'timestamp' => $timestamp,
                'requestContent' => $requestContent,
            ],
            $context
        );
    }

    /**
     * @param array<string, mixed|null> $payload
     */
    private function updateLogIfItExists(array $payload, Context $context): void
    {
        try {
            $this->webhookEventLogRepository->update([$payload], $context);
        } catch (WriteTypeIntendException $e) {
            // ignore, as that indicates the log entry was already deleted, in that case we don't need to update it
        }
    }
}
