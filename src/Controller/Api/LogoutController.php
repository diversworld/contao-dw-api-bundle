<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/logout', name: 'api_logout_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class LogoutController
{
    private ?TokenStorageInterface $tokenStorage = null;
    private ?RequestStack $requestStack = null;
    private ?ContaoFramework $framework = null;

    #[Route('', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
        // Security Token im Storage löschen
        $this->getTokenStorage()->setToken(null);

        // Session abrufen und bereinigen
        $session = $this->getRequestStack()->getSession();
        $session->remove('_security_frontend');
        $session->invalidate();

        return new JsonResponse([
            'success' => true
        ]);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = System::getContainer()->get('contao.framework');
        }

        return $this->framework;
    }

    private function getTokenStorage(): TokenStorageInterface
    {
        if (null === $this->tokenStorage) {
            $this->tokenStorage = System::getContainer()->get('security.token_storage');
        }

        return $this->tokenStorage;
    }

    private function getRequestStack(): RequestStack
    {
        if (null === $this->requestStack) {
            $this->requestStack = System::getContainer()->get('request_stack');
        }

        return $this->requestStack;
    }
}
