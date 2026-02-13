<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/logout', name: 'api_logout_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class LogoutController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
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

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = \Contao\System::getContainer()->get(ContaoFramework::class);
        }

        return $this->framework;
    }
}
