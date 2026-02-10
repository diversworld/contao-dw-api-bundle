<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\MemberModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/password', name: 'api_password_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class PasswordController extends AbstractController
{
    public function __construct(
        private readonly Security        $security,
        private readonly ContaoFramework $framework
    )
    {
    }

    #[Route('', name: 'change', methods: ['PATCH'])]
    public function change(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        $this->framework->initialize();
        $frontendUser = $this->security->getUser();

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
        if (!$passwordHasher->isPasswordValid($frontendUser, $current)) {
            return new JsonResponse(['error' => 'Current password incorrect'], 400);
        }

        // MemberModel neu laden
        $memberModel = MemberModel::findByPk($frontendUser->id);

        if (!$memberModel) {
            return new JsonResponse(['error' => 'Member not found'], 404);
        }

        // Passwort korrekt hashen
        $hashedPassword = $passwordHasher->hashPassword($frontendUser, $new);

        $memberModel->password = $hashedPassword;
        $memberModel->save();

        return new JsonResponse(['success' => true]);
    }
}
