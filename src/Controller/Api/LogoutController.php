<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/logout', name: 'api_logout_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class LogoutController
{
    private const FRONTEND_SECURITY_SESSION_KEY = '_security_contao_frontend';

    public function __construct(
        private readonly ContaoFramework       $framework,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack          $requestStack,
    )
    {
    }

    #[Route('', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $this->framework->initialize();
        $this->tokenStorage->setToken(null);

        // Keep this key aligned with LoginController and the Contao frontend firewall.
        $session = $this->requestStack->getSession();
        $session->remove(self::FRONTEND_SECURITY_SESSION_KEY);
        $session->invalidate();

        return new JsonResponse([
            'success' => true
        ]);
    }

}
