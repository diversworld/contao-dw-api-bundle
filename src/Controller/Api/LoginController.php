<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FrontendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Contao\StringUtil;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[Route('/api/login', name: 'api_login_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class LoginController
{
    private ?TokenStorageInterface $tokenStorage = null;
    private ?RequestStack $requestStack = null;
    private ?ContaoFramework $framework = null;

    #[Route('', name: 'login_check', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
        $content = json_decode($request->getContent(), true);

        if (!$content) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Check if API is enabled
        $config = DcConfigModel::findOneBy('published', '1');
        if (!$config || !$config->activateApi) {
            return new JsonResponse(['error' => 'API is currently disabled'], 503);
        }

        $username = $content['username'] ?? '';
        $password = $content['password'] ?? '';

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

        // Validate password
        $passwordHasher = System::getContainer()
            ->get('security.password_hasher_factory')
            ->getPasswordHasher(FrontendUser::class);

        if (!$passwordHasher->verify((string)$user->password, $password)) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        // Symfony Security Token erzeugen
        $token = new UsernamePasswordToken(
            $user,
            'frontend',
            $user->getRoles()
        );

        $this->getTokenStorage()->setToken($token);

        // Session speichern
        $session = $this->getRequestStack()->getSession();
        $session->set('_security_frontend', serialize($token));
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
                'memberGroups' => array_map('intval', \Contao\StringUtil::deserialize($user->groups, true)),
                'role' => $this->getMemberRole($user),
                'isTrainingManager' => $this->isTrainingManager($user),
            ]
        ]);
    }

    private function getMemberRole($user): string
    {
        $this->getFramework()->initialize();
        if (!$user instanceof FrontendUser) {
            return 'member';
        }

        $groups = \Contao\StringUtil::deserialize($user->groups, true);
        $db = \Contao\Database::getInstance();
        $configResult = $db->prepare("SELECT instructor_groups FROM tl_dc_config WHERE published='1' LIMIT 1")->execute();

        $instructorGroups = [];
        if ($configResult->numRows > 0) {
            $instructorGroups = \Contao\StringUtil::deserialize($configResult->instructor_groups, true);
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
        $this->getFramework()->initialize();

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
