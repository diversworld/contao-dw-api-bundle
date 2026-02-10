<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/instructor', name: 'api_instructor_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class InstructorController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('/approve/{id}', name: 'approve', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function approve(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        if (!$this->isInstructor()) {
            return new JsonResponse(['error' => 'Forbidden: Instructor role required'], 403);
        }

        $enrollment = DcCourseStudentsModel::findByPk($id);

        if (!$enrollment) {
            return new JsonResponse(['error' => 'Enrollment not found'], 404);
        }

        $enrollment->status = 'active';
        $enrollment->tstamp = time();

        if (!$enrollment->save()) {
            return new JsonResponse(['error' => 'Could not approve enrollment'], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/reject/{id}', name: 'reject', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function reject(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        if (!$this->isInstructor()) {
            return new JsonResponse(['error' => 'Forbidden: Instructor role required'], 403);
        }

        $enrollment = DcCourseStudentsModel::findByPk($id);

        if (!$enrollment) {
            return new JsonResponse(['error' => 'Enrollment not found'], 404);
        }

        // Using 'dropped' as a status for rejection based on tl_dc_course_students DCA
        $enrollment->status = 'dropped';
        $enrollment->tstamp = time();

        if (!$enrollment->save()) {
            return new JsonResponse(['error' => 'Could not reject enrollment'], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    private function isInstructor(): bool
    {
        $this->getFramework()->initialize();
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return false;
        }

        $groups = StringUtil::deserialize($user->groups, true);
        $db = \Contao\Database::getInstance();
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
                return true;
            }
        }

        return false;
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = \Contao\System::getContainer()->get(ContaoFramework::class);
        }

        return $this->framework;
    }
}
