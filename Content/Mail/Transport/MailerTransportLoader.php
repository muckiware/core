<?php declare(strict_types=1);

namespace Muckiware\Core\Content\Mail\Transport;

use Doctrine\DBAL\Exception\DriverException;
use Shopware\Core\Content\Mail\MailException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Transports;

/**
 * Simplified mailer transport loader without Checkout/document attachment dependencies.
 * Replaces Shopware\Core\Content\Mail\Transport\MailerTransportLoader for non-webshop use.
 *
 * @internal
 */
class MailerTransportLoader
{
    private const VALID_SENDMAIL_OPTIONS = ['-bs', '-i', '-t'];

    public function __construct(
        private readonly Transport $envBasedTransport,
        private readonly SystemConfigService $configService,
    ) {
    }

    /**
     * @param array<string, string> $dsns
     */
    public function fromStrings(array $dsns): Transports
    {
        $transports = [];
        foreach ($dsns as $name => $dsn) {
            $transports[$name] = $this->fromString($dsn);
        }

        return new Transports($transports);
    }

    public function fromString(string $dsn): TransportInterface
    {
        try {
            $transportConfig = trim($this->configService->getString('core.mailerSettings.emailAgent'));

            if ($transportConfig === '') {
                return $this->envBasedTransport->fromString($dsn);
            }
        } catch (DriverException) {
            return $this->envBasedTransport->fromString($dsn);
        }

        return $this->create();
    }

    private function create(): TransportInterface
    {
        $emailAgent = $this->configService->getString('core.mailerSettings.emailAgent');

        return match ($emailAgent) {
            'smtp' => $this->createSmtpTransport(),
            'local' => $this->createSendmailTransport(),
            default => throw MailException::givenMailAgentIsInvalid($emailAgent),
        };
    }

    private function createSmtpTransport(): TransportInterface
    {
        $encryption = $this->configService->getString('core.mailerSettings.encryption');

        $dsn = new Dsn(
            $encryption === 'ssl' ? 'smtps' : 'smtp',
            $this->configService->getString('core.mailerSettings.host'),
            $this->configService->getString('core.mailerSettings.username'),
            $this->configService->getString('core.mailerSettings.password'),
            $this->configService->getInt('core.mailerSettings.port'),
            $encryption !== null ? [] : ['verify_peer' => 0]
        );

        return $this->envBasedTransport->fromDsnObject($dsn);
    }

    private function createSendmailTransport(): TransportInterface
    {
        $sendMailOptions = trim($this->configService->getString('core.mailerSettings.sendMailOptions'));

        if ($sendMailOptions === '') {
            $sendMailOptions = '-t -i';
        }

        $options = preg_split('/\s+/', $sendMailOptions) ?: [$sendMailOptions];

        foreach ($options as $option) {
            if (!\in_array(trim($option), self::VALID_SENDMAIL_OPTIONS, true)) {
                throw MailException::givenSendMailOptionIsInvalid($option, self::VALID_SENDMAIL_OPTIONS);
            }
        }

        $dsn = new Dsn(
            scheme: 'sendmail',
            host: '',
            options: ['command' => '/usr/sbin/sendmail ' . $sendMailOptions],
        );

        return $this->envBasedTransport->fromDsnObject($dsn);
    }
}
