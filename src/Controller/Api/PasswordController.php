<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\MemberModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/password', name: 'api_password_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class PasswordController
{
    private ?Security $security = null;
    private ?ContaoFramework $framework = null;

    #[Route('', name: 'change', methods: ['PATCH'])]
    public function change(Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
        $frontendUser = $this->getSecurity()->getUser();

        if (!$frontendUser instanceof PasswordAuthenticatedUserInterface) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);

        $current = $data['currentPassword'] ?? null;
        $new = $data['newPassword'] ?? null;

        if (!$current || !$new) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        // Aktuellen User validieren
        $passwordHasher = System::getContainer()
            ->get('security.password_hasher_factory')
            ->getPasswordHasher(\Contao\FrontendUser::class);

        if (!$passwordHasher->verify((string)$frontendUser->password, (string)$current)) {
            return new JsonResponse(['error' => 'Current password incorrect'], 400);
        }

        // MemberModel neu laden
        $memberModel = MemberModel::findByPk($frontendUser->id);

        if (!$memberModel) {
            return new JsonResponse(['error' => 'Member not found'], 404);
        }

        // Passwort korrekt hashen
        $hashedPassword = $passwordHasher->hash((string)$new);

        $memberModel->password = $hashedPassword;
        $memberModel->save();

        return new JsonResponse(['success' => true]);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = System::getContainer()->get('contao.framework');
        }

        return $this->framework;
    }

    private function getSecurity(): Security
    {
        if (null === $this->security) {
            $this->security = System::getContainer()->get('security.helper');
        }

        return $this->security;
    }
}
