<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\MemberModel;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcDiveCourseModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseModulesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseExercisesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentExercisesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Contao\System;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/instructor', name: 'api_instructor_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class InstructorController
{
    private ?ContaoFramework $framework = null;


    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $this->getFramework()->initialize();
        if (!$this->isTrainingManager()) {
            return new JsonResponse(['error' => 'Forbidden: Training Manager role required'], 403);
        }

        $config = DcConfigModel::findOneBy('published', '1');
        $options = $config ? StringUtil::deserialize($config->dashboard_options, true) : [];

        $result = [];
        if (in_array('courses', $options, true)) {
            $events = DcCourseEventModel::findBy(['published=?'], ['1'], ['order' => 'dateStart DESC']);

            if ($events !== null) {
                foreach ($events as $event) {
                    $course = DcDiveCourseModel::findByPk($event->course_id);
                    $instructor = MemberModel::findByPk($event->instructor);

                    $studentsData = $this->loadStudentsForEvent((int)$event->id, (int)$event->course_id);

                    $result[] = [
                        'id' => (int)$event->id,
                        'title' => $event->title,
                        'course_title' => $course ? $course->title : '',
                        'dateStart' => (int)$event->dateStart,
                        'dateEnd' => (int)$event->dateEnd,
                        'instructor_id' => (int)$event->instructor,
                        'instructor_name' => $instructor ? trim($instructor->firstname . ' ' . $instructor->lastname) : '',
                        'students' => $studentsData
                    ];
                }
            }
        }

        $workload = [];
        if (in_array('workload', $options, true)) {
            // Use result from courses if already loaded, otherwise load minimal data
            $eventsForWorkload = $result;
            if (empty($eventsForWorkload) && !in_array('courses', $options, true)) {
                $events = DcCourseEventModel::findBy(['published=?'], ['1']);
                if ($events !== null) {
                    foreach ($events as $event) {
                        $instructor = MemberModel::findByPk($event->instructor);
                        $eventsForWorkload[] = [
                            'instructor_name' => $instructor ? trim($instructor->firstname . ' ' . $instructor->lastname) : '',
                        ];
                    }
                }
            }

            foreach ($eventsForWorkload as $event) {
                $name = $event['instructor_name'] ?: 'Nicht zugewiesen';
                if (!isset($workload[$name])) {
                    $workload[$name] = 0;
                }
                $workload[$name]++;
            }
        }

        return new JsonResponse([
            'courses' => $result,
            'workload' => $workload,
            'options' => $options
        ]);
    }

    private function isTrainingManager(): bool
    {
        $this->getFramework()->initialize();
        $user = $this->getSecurity()->getUser();

        if (!$user instanceof FrontendUser) {
            return false;
        }

        $config = DcConfigModel::findOneBy('published', '1');
        if ($config === null) {
            return false;
        }

        $trainingManagers = StringUtil::deserialize($config->training_manager, true);

        return in_array((int)$user->id, array_map('intval', $trainingManagers), true);
    }

    private function loadStudentsForEvent(int $eventId, int $courseId): array
    {
        $students = DcCourseStudentsModel::findBy(['event_id=?', 'published=?'], [$eventId, '1']);
        $list = [];

        if ($students === null) {
            return $list;
        }

        $totalExercises = $this->getTotalExercisesCount($courseId);

        foreach ($students as $enrollment) {
            $student = DcStudentsModel::findByPk($enrollment->pid);
            if (!$student) continue;

            $completedExercises = DcStudentExercisesModel::countBy(['pid=?', 'status=?'], [$enrollment->id, 'ok']);
            $progressDetails = $this->getStudentProgressDetails((int)$enrollment->id, $courseId);

            $list[] = [
                'name' => trim($student->firstname . ' ' . $student->lastname),
                'status' => $enrollment->status,
                'progress' => ($totalExercises > 0) ? round(($completedExercises / $totalExercises) * 100) : 0,
                'completed' => (int)$completedExercises,
                'total' => $totalExercises,
                'details' => $progressDetails
            ];
        }

        return $list;
    }

    private function getTotalExercisesCount(int $courseId): int
    {
        $modules = DcCourseModulesModel::findBy('pid', $courseId);
        if (!$modules) return 0;

        $count = 0;
        foreach ($modules as $module) {
            $count += (int)DcCourseExercisesModel::countBy('pid', $module->id);
        }

        return $count;
    }

    private function getStudentProgressDetails(int $enrollmentId, int $courseId): array
    {
        $modules = DcCourseModulesModel::findBy('pid', $courseId, ['order' => 'sorting']);
        $details = [];

        if (!$modules) return $details;

        foreach ($modules as $module) {
            $exercises = DcCourseExercisesModel::findBy('pid', $module->id, ['order' => 'sorting']);
            $exerciseList = [];

            if ($exercises) {
                foreach ($exercises as $exercise) {
                    $statusRecord = DcStudentExercisesModel::findOneBy(['pid=?', 'exercise_id=?'], [$enrollmentId, $exercise->id]);
                    $exerciseList[] = [
                        'title' => $exercise->title,
                        'status' => $statusRecord ? $statusRecord->status : 'pending'
                    ];
                }
            }

            $details[] = [
                'title' => $module->title,
                'exercises' => $exerciseList
            ];
        }

        return $details;
    }

    #[Route('/approve/{id}', name: 'approve', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function approve(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        if (!$this->isTrainingManager()) {
            return new JsonResponse(['error' => 'Forbidden: Training Manager role required'], 403);
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
        if (!$this->isTrainingManager()) {
            return new JsonResponse(['error' => 'Forbidden: Training Manager role required'], 403);
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
        $user = $this->getSecurity()->getUser();

        if (!$user instanceof FrontendUser) {
            return false;
        }

        $groups = StringUtil::deserialize($user->groups, true);
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
                return true;
            }
        }

        return false;
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
        return System::getContainer()->get('security.helper');
    }
}
