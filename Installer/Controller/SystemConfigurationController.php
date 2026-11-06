<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use Muckiware\Core\Installer\Configuration\SystemConfigurationService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Configuration\AdminConfigurationService;
use Shopware\Core\Installer\Configuration\EnvConfigWriter;
use Shopware\Core\Installer\Controller\InstallerController;
use Shopware\Core\Installer\Database\BlueGreenDeploymentService;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Replaces the Shopware shop configuration step (route "installer.configuration"): system name, email,
 * default language and admin user. Snippets for a default language other than de-DE/en-GB are installed
 * in the translation step afterwards.
 *
 * @internal
 *
 * @phpstan-import-type SupportedLanguages from InstallerController
 */
#[Package('framework')]
final class SystemConfigurationController extends InstallerController
{
    private const BUILT_IN_LOCALES = ['de-DE', 'en-GB'];

    /**
     * @param SupportedLanguages $supportedLanguages
     */
    public function __construct(
        private readonly DatabaseConnectionFactory $connectionFactory,
        private readonly EnvConfigWriter $envConfigWriter,
        private readonly SystemConfigurationService $systemConfigurationService,
        private readonly AdminConfigurationService $adminConfigurationService,
        private readonly array $supportedLanguages
    ) {
    }

    #[Route(path: '/installer/configuration', name: 'installer.configuration', methods: ['GET', 'POST'])]
    public function systemConfiguration(Request $request): Response
    {
        $session = $request->getSession();
        $connectionInfo = $session->get(DatabaseConnectionInformation::class);

        if (!$connectionInfo instanceof DatabaseConnectionInformation) {
            return $this->redirectToRoute('installer.database-configuration');
        }

        $userLocale = (string) $request->attributes->get('_locale');
        $error = null;

        if ($request->isMethod(Request::METHOD_POST)) {
            try {
                return $this->configure($request, $connectionInfo, $userLocale);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        if (!$request->request->has('config_shop_language')) {
            $request->request->set('config_shop_language', $this->supportedLanguages[$userLocale]['id']);
        }

        return $this->renderInstaller(
            '@Installer/installer/system-configuration.html.twig',
            [
                'error' => $error,
                'languageIsos' => $this->getSystemDefaultLanguageOptions(),
                'parameters' => $request->request->all(),
            ]
        );
    }

    private function configure(Request $request, DatabaseConnectionInformation $connectionInfo, string $userLocale): Response
    {
        $session = $request->getSession();
        $connection = $this->connectionFactory->getConnection($connectionInfo);

        $adminUser = [
            'email' => (string) $request->request->get('config_admin_email'),
            'username' => (string) $request->request->get('config_admin_username'),
            'firstName' => (string) $request->request->get('config_admin_firstName'),
            'lastName' => (string) $request->request->get('config_admin_lastName'),
            'password' => (string) $request->request->get('config_admin_password'),
            'localeId' => $this->resolveLocaleId($connection, $userLocale),
        ];

        $system = $this->getSystemData($request, $session);

        // EnvConfigWriter only reads schema, host, basePath and blueGreenDeployment of the Shopware shop data
        // @phpstan-ignore argument.type
        $this->envConfigWriter->writeConfig($connectionInfo, $system);

        // create admin user first, if there is a validation error we don't need to update the system
        $this->adminConfigurationService->createAdmin($adminUser, $connection);
        $this->systemConfigurationService->updateSystem($system, $connection);

        $session->set('ADMIN_USER', $adminUser);

        if (\in_array($system['locale'], self::BUILT_IN_LOCALES, true)) {
            $session->remove(DatabaseConnectionInformation::class);

            return $this->redirectToRoute('installer.finish', ['completed' => true]);
        }

        $session->set('SELECTED_LANGUAGES', [$system['locale']]);

        return $this->redirectToRoute('installer.translation');
    }

    /**
     * @return array{name: string, locale: string, email: string, host: string, basePath: string, schema: string, blueGreenDeployment: bool}
     */
    private function getSystemData(Request $request, SessionInterface $session): array
    {
        return [
            'name' => (string) $request->request->get('config_shopName'),
            'locale' => (string) $request->request->get('config_shop_language'),
            'email' => (string) $request->request->get('config_mail'),
            'host' => (string) $_SERVER['HTTP_HOST'],
            'schema' => $this->getSchema(),
            'basePath' => str_replace('/index.php', '', (string) $_SERVER['SCRIPT_NAME']),
            'blueGreenDeployment' => (bool) $session->get(BlueGreenDeploymentService::ENV_NAME),
        ];
    }

    private function getSchema(): string
    {
        if (\array_key_exists('REQUEST_SCHEME', $_SERVER)) {
            return (string) $_SERVER['REQUEST_SCHEME'];
        }

        // This is for supporting Apache 2.2
        if (\array_key_exists('HTTPS', $_SERVER) && mb_strtolower((string) $_SERVER['HTTPS']) === 'on') {
            return 'https';
        }

        return 'http';
    }

    /**
     * @return SupportedLanguages
     */
    private function getSystemDefaultLanguageOptions(): array
    {
        $systemDefaultLanguageOptions = $this->supportedLanguages;
        $systemDefaultLanguageOptions['de-CH'] = ['id' => 'de-CH', 'label' => 'Deutsch (Schweiz)'];
        $systemDefaultLanguageOptions['de-AT'] = ['id' => 'de-AT', 'label' => 'Deutsch (Österreich)'];
        ksort($systemDefaultLanguageOptions);

        return $systemDefaultLanguageOptions;
    }

    private function resolveLocaleId(Connection $connection, string $userLocale): string
    {
        $userLocale = $this->supportedLanguages[$userLocale]['id'];

        return (string) $connection->fetchOne('SELECT id FROM locale WHERE code = :locale', ['locale' => $userLocale]);
    }
}
