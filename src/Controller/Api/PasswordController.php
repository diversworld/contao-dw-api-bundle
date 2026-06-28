<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\MemberModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/password', name: 'api_password_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class PasswordController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ContaoFramework                $framework,
        private readonly Security                       $security,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    )
    {
    }

    #[Route('', name: 'change', methods: ['PATCH'])]
    public function change(Request $request): JsonResponse
    {
        $this->framework->initialize();
        $frontendUser = $this->security->getUser();

        if (!$frontendUser instanceof FrontendUser || !$frontendUser instanceof PasswordAuthenticatedUserInterface) {
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

        // Reload the persistent model; the security user object itself is not saved.
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
