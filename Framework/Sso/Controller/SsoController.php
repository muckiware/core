<?php declare(strict_types=1);

namespace Muckiware\Core\Framework\Sso\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\Exceptions\SsoUserNotFoundException;
use Shopware\Core\Framework\Sso\LoginResponseService;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\SsoService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopware\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopware\Core\Framework\Sso\StateValidator;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use Shopware\Core\Framework\Sso\Controller\SsoController as OrgSsoController;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class SsoController extends OrgSsoController
{
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly LoginConfigService $loginConfigService,
        private readonly LoginResponseService $loginResponseService,
        private readonly StateValidator $stateValidator,
        private readonly SsoUserService $ssoUserService,
        private readonly SsoUserInvitationMailService $ssoUserInvitationMailService,
        private readonly SsoService $ssoService,
    ) {
        $checker =1;
        parent::__construct(
            $this->authorizationServer,
            $this->psrHttpFactory,
            $this->loginConfigService,
            $this->loginResponseService,
            $this->stateValidator,
            $this->ssoUserService,
            $this->ssoUserInvitationMailService,
            $this->ssoService,
        );
    }
}
