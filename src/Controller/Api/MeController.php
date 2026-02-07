<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\MemberModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me', name: 'api_me_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class MeController extends AbstractController
{
    public function __construct(
        private readonly Security $security
    ) {
    }

    #[Route('', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        return new JsonResponse([
            'id' => (int) $user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'street' => $user->street,
            'postal' => $user->postal,
            'city' => $user->city,
            'phone' => $user->phone,
            'mobile' => $user->mobile,
            'dateOfBirth' => $user->dateOfBirth,
        ]);
    }
    #[Route('', name: 'update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->security->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Nur erlaubte Felder ändern
        $fields = ['firstname', 'lastname', 'email', 'street', 'postal', 'city', 'phone', 'mobile', 'dateOfBirth'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $user->$field = ($field === 'dateOfBirth') ? (int) $data[$field] : (string) $data[$field];
            }
        }

        $user->save();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/password', name: 'change_password', methods: ['PATCH'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {

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

        // 👇 WICHTIG: MemberModel neu laden
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
