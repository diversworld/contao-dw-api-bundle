<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\Database;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\System;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me', name: 'api_me_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class MeController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security        $security,
    )
    {
    }

    #[Route('', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        return new JsonResponse([
            'id' => (int)$user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'street' => $user->street,
            'postal' => $user->postal,
            'city' => $user->city,
            'phone' => $user->phone,
            'mobile' => $user->mobile,
            'dateOfBirth' => (int)$user->dateOfBirth,
            'memberGroups' => array_map('intval', StringUtil::deserialize($user->groups, true)),
            'role' => $this->getMemberRole($user),
            'isTrainingManager' => $this->isTrainingManager($user),
        ]);
    }

    private function getMemberRole($user): string
    {
        $this->framework->initialize();
        if (!$user instanceof FrontendUser) {
            return 'member';
        }

        $groups = StringUtil::deserialize($user->groups, true);
        $db = Database::getInstance();
        $configResult = $db->prepare("SELECT instructor_groups FROM tl_dc_config WHERE published='1' LIMIT 1")->execute();

        $instructorGroups = [];
        if ($configResult->numRows > 0) {
            $instructorGroups = StringUtil::deserialize($configResult->instructor_groups, true);
        }

        if (empty($instructorGroups)) {
            $instructorGroups = ['2', '3'];
        }

        foreach ($instructorGroups as $groupId) {
            if (in_array((string)$groupId, $groups, true)) {
                return 'instructor';
            }
        }

        return 'member';
    }

    private function isTrainingManager($user): bool
    {
        $this->framework->initialize();

        if (!$user instanceof FrontendUser) {
            return false;
        }

        $groups = StringUtil::deserialize($user->groups, true);
        $db = Database::getInstance();
        $configResult = $db->prepare("SELECT instructor_groups, training_manager FROM tl_dc_config WHERE published='1' LIMIT 1")->execute();

        if ($configResult->numRows < 1) {
            return false;
        }

        // 1. Check if user ID is explicitly listed
        $trainingManagers = StringUtil::deserialize($configResult->training_manager, true);
        if (in_array((int)$user->id, array_map('intval', $trainingManagers), true)) {
            return true;
        }

        // 2. Check if user is in one of the instructor groups
        $instructorGroups = StringUtil::deserialize($configResult->instructor_groups, true);
        if (!empty($instructorGroups)) {
            foreach ($instructorGroups as $groupId) {
                if (in_array((string)$groupId, $groups, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    #[Route('', name: 'update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Nur erlaubte Felder ändern
        $fields = ['firstname', 'lastname', 'email', 'street', 'postal', 'city', 'phone', 'mobile', 'dateOfBirth'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $user->$field = ($field === 'dateOfBirth') ? (int)$data[$field] : (string)$data[$field];
            }
        }

        // 👇 WICHTIG: MemberModel laden um zu speichern
        $memberModel = MemberModel::findByPk($user->id);
        if (!$memberModel) {
            return new JsonResponse(['error' => 'Member not found'], 404);
        }

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $memberModel->$field = ($field === 'dateOfBirth') ? (int)$data[$field] : (string)$data[$field];
            }
        }

        $memberModel->save();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/password', name: 'change_password', methods: ['PATCH'])]
    public function changePassword(Request $request): JsonResponse
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
        $passwordHasher = System::getContainer()
            ->get('security.password_hasher_factory')
            ->getPasswordHasher(FrontendUser::class);

        if (!$passwordHasher->verify((string)$frontendUser->password, (string)$current)) {
            return new JsonResponse(['error' => 'Current password incorrect'], 400);
        }

        // 👇 WICHTIG: MemberModel neu laden
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
}
