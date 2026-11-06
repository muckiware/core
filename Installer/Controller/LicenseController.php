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

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Controller\InstallerController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Muckidrive has no Shopware license step. The route is kept because the Shopware database configuration
 * view links back to it, so it leads back to the requirements step.
 *
 * @internal
 */
#[Package('framework')]
final class LicenseController extends InstallerController
{
    #[Route(path: '/installer/license', name: 'installer.license', methods: ['GET', 'POST'])]
    public function license(): Response
    {
        return $this->redirectToRoute('installer.requirements');
    }
}
