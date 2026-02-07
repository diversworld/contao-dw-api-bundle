<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/logout', name: 'api_logout_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class LogoutController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack
    ) {
    }

    #[Route('', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // Security Token im Storage löschen
        $this->tokenStorage->setToken(null);

        // Session abrufen und bereinigen
        $session = $this->requestStack->getSession();
        $session->remove('_security_frontend');
        $session->invalidate();

        return new JsonResponse([
            'success' => true
        ]);
    }
}
