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
use Shopware\Core\Installer\Requirements\RequirementsValidatorInterface;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Replaces the Shopware route: continues with the database configuration, the license step is skipped.
 *
 * @internal
 */
#[Package('framework')]
final class RequirementsController extends InstallerController
{
    /**
     * @param iterable<RequirementsValidatorInterface> $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    #[Route(path: '/installer/requirements', name: 'installer.requirements', methods: ['GET', 'POST'])]
    public function requirements(Request $request): Response
    {
        $checks = new RequirementsCheckCollection();

        foreach ($this->validators as $validator) {
            $checks = $validator->validateRequirements($checks);
        }

        if ($request->isMethod(Request::METHOD_POST) && !$checks->hasError()) {
            return $this->redirectToRoute('installer.database-configuration');
        }

        return $this->renderInstaller(
            '@Installer/installer/requirements.html.twig',
            [
                'requirementChecks' => $checks,
                'noWayBack' => $request->getSession()->has('extendSteps'),
            ]
        );
    }
}
