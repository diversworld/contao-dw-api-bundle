<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me', name: 'api_me_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class MeController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security        $security,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
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

        $groups = array_map('strval', StringUtil::deserialize($user->groups, true));
        $config = DcConfigModel::findOneBy('published', '1');

        $instructorGroups = [];
        if ($config !== null) {
            $instructorGroups = StringUtil::deserialize($config->instructor_groups, true);
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

        $groups = array_map('strval', StringUtil::deserialize($user->groups, true));
        $config = DcConfigModel::findOneBy('published', '1');

        if (null === $config) {
            return false;
        }

        // A training manager can either be listed explicitly or inherit access
        // through one of the configured instructor groups.
        $trainingManagers = StringUtil::deserialize($config->training_manager, true);
        if (in_array((int)$user->id, array_map('intval', $trainingManagers), true)) {
            return true;
        }

        $instructorGroups = StringUtil::deserialize($config->instructor_groups, true);
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

        $data = $this->decodeJsonPayload($request);

        if (null === $data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Only profile fields owned by the member API are accepted here.
        $fields = ['firstname', 'lastname', 'email', 'street', 'postal', 'city', 'phone', 'mobile', 'dateOfBirth'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $user->$field = ($field === 'dateOfBirth') ? (int)$data[$field] : (string)$data[$field];
            }
        }

        // Reload the persistent model; the security user object is only the
        // authenticated session representation.
        $memberModel = MemberModel::findByPk($user->id);
        if (!$memberModel) {
            return new JsonResponse(['error' => 'Member not found'], 404);
        }

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $memberModel->$field = ($field === 'dateOfBirth') ? (int)$data[$field] : (string)$data[$field];
            }
        }

        if (!$memberModel->save()) {
            return new JsonResponse(['error' => 'Could not save member'], 500);
        }

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

        $data = $this->decodeJsonPayload($request);

        if (null === $data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $current = $data['currentPassword'] ?? null;
        $new = $data['newPassword'] ?? null;

        if (!$current || !$new) {
            return new JsonResponse(['error' => 'Missing fields'], 400);
        }

        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($frontendUser);

        if (!$passwordHasher->verify((string)$frontendUser->password, (string)$current)) {
            return new JsonResponse(['error' => 'Current password incorrect'], 400);
        }

        // Reload the persistent model before saving the new password hash.
        $memberModel = MemberModel::findByPk($frontendUser->id);

        if (!$memberModel) {
            return new JsonResponse(['error' => 'Member not found'], 404);
        }

        $hashedPassword = $passwordHasher->hash((string)$new);

        $memberModel->password = $hashedPassword;
        $memberModel->save();

        return new JsonResponse(['success' => true]);
    }
}
