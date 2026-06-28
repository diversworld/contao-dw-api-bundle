<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FrontendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[Route('/api/login', name: 'api_login_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class LoginController
{
    use ApiControllerTrait;

    private const FRONTEND_FIREWALL = 'contao_frontend';
    private const FRONTEND_SECURITY_SESSION_KEY = '_security_contao_frontend';

    public function __construct(
        private readonly ContaoFramework       $framework,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack          $requestStack,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    )
    {
    }

    #[Route('', name: 'login_check', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $this->framework->initialize();
        $content = $this->decodeJsonPayload($request);

        if (null === $content) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Check if API is enabled
        $config = DcConfigModel::findOneBy('published', '1');
        if (!$config || !$config->activateApi) {
            return new JsonResponse(['error' => 'API is currently disabled'], 503);
        }

        $username = trim((string)($content['username'] ?? ''));
        $password = (string)($content['password'] ?? '');

        if (empty($username) || empty($password)) {
            return new JsonResponse(['error' => 'Missing username or password'], 400);
        }

        $user = FrontendUser::loadUserByIdentifier($username);

        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        // Check if account is disabled
        if ($user->disable || ($user->start && $user->start > time()) || ($user->stop && $user->stop <= time())) {
            return new JsonResponse(['error' => 'Account is disabled'], 403);
        }

        $passwordHasher = $this->passwordHasherFactory->getPasswordHasher($user);

        if (!$passwordHasher->verify((string)$user->password, $password)) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        // The firewall name must match Contao's frontend firewall; otherwise
        // Symfony will not restore the user from the session on the next request.
        $token = new UsernamePasswordToken(
            $user,
            self::FRONTEND_FIREWALL,
            $user->getRoles()
        );

        $this->tokenStorage->setToken($token);

        $session = $this->requestStack->getSession();
        $session->migrate(true);
        $session->set(self::FRONTEND_SECURITY_SESSION_KEY, serialize($token));
        $session->save();

        return new JsonResponse([
            'success' => true,
            'member' => [
                'id' => (int)$user->id,
                'username' => $user->username,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'dateOfBirth' => (int)$user->dateOfBirth,
                'memberGroups' => array_map('intval', StringUtil::deserialize($user->groups, true)),
                'role' => $this->getMemberRole($user),
                'isTrainingManager' => $this->isTrainingManager($user),
            ]
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

        $config = DcConfigModel::findOneBy('published', '1');

        if (null === $config) {
            return false;
        }

        $trainingManagers = StringUtil::deserialize($config->training_manager, true);

        return in_array((int)$user->id, array_map('intval', $trainingManagers), true);
    }
}
