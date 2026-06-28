<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentExercisesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcDiveCourseModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseExercisesModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/progress', name: 'api_progress_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class ProgressController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security        $security,
    )
    {
    }

    /**
     * Get progress for the current logged in user (student view)
     */
    #[Route('', name: 'student', methods: ['GET'])]
    public function studentProgress(): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();
        if (!$user instanceof FrontendUser) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $student = DcStudentsModel::findOneBy('memberId', $user->id);
        if (!$student) {
            return new JsonResponse(['error' => 'No student profile found'], 404);
        }

        $enrollments = DcCourseStudentsModel::findBy('pid', $student->id);
        if (null === $enrollments) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($enrollments as $enrollment) {
            $course = DcDiveCourseModel::findByPk($enrollment->course_id);

            $enrollmentData = $enrollment->row();
            foreach (['tstamp', 'registered_on', 'dateBrevet', 'start', 'stop'] as $f) {
                if (isset($enrollmentData[$f]) && $enrollmentData[$f] !== '') {
                    $enrollmentData[$f] = (int)$enrollmentData[$f];
                }
            }
            if (isset($enrollmentData['payed'])) {
                $enrollmentData['payed'] = (bool)$enrollmentData['payed'];
            }
            if (isset($enrollmentData['brevet'])) {
                $enrollmentData['brevet'] = (bool)$enrollmentData['brevet'];
            }
            if (isset($enrollmentData['published'])) {
                $enrollmentData['published'] = (bool)$enrollmentData['published'];
            }
            if (isset($enrollmentData['notes'])) {
                $enrollmentData['notes'] = (string)$enrollmentData['notes'];
            }

            $courseData = $course ? $course->row() : null;
            if ($courseData) {
                if (isset($courseData['singleSRC']) && $courseData['singleSRC']) {
                    $courseData['singleSRC'] = StringUtil::binToUuid($courseData['singleSRC']);
                }

                foreach (['tstamp', 'start', 'stop'] as $f) {
                    if (isset($courseData[$f]) && $courseData[$f] !== '') {
                        $courseData[$f] = (int)$courseData[$f];
                    }
                }
                if (isset($courseData['published'])) {
                    $courseData['published'] = (bool)$courseData['published'];
                }
            }

            $item = [
                'id' => (int)$enrollment->id,
                'enrollment_id' => (int)$enrollment->id,
                'enrollment' => $enrollmentData,
                'course' => $courseData,
                'exercises' => []
            ];

            $exercises = DcStudentExercisesModel::findBy('pid', $enrollment->id);
            if ($exercises) {
                foreach ($exercises as $exercise) {
                    $exRow = $exercise->row();
                    foreach (['tstamp', 'dateCompleted', 'start', 'stop'] as $f) {
                        if (isset($exRow[$f]) && $exRow[$f] !== '') {
                            $exRow[$f] = (int)$exRow[$f];
                        }
                    }

                    if (isset($exRow['published'])) {
                        $exRow['published'] = (bool)$exRow['published'];
                    }
                    if (isset($exRow['notes'])) {
                        $exRow['notes'] = (string)$exRow['notes'];
                    }

                    // Add exercise title
                    $exerciseModel = DcCourseExercisesModel::findByPk($exercise->exercise_id);
                    $exRow['title'] = $exerciseModel ? html_entity_decode($exerciseModel->title) : 'Unknown Exercise';

                    $item['exercises'][] = $exRow;
                }
            }
            $data[] = $item;
        }

        return new JsonResponse($data);
    }

    /**
     * Get students and their progress for instructor
     */
    #[Route('/instructor', name: 'instructor', methods: ['GET'])]
    public function instructorStudents(): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();
        if (!$user instanceof FrontendUser) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Wir sammeln alle enrollments (tl_dc_course_students), bei denen der User
        // entweder im Course-Template, im konkreten Event oder direkt in einer Übung als Instructor steht.

        $db = \Contao\Database::getInstance();
        $assignments = $db->prepare(
            "SELECT DISTINCT cs.id
             FROM tl_dc_course_students cs
             INNER JOIN tl_dc_students s ON s.id = cs.pid
             LEFT JOIN tl_dc_course_event ce ON ce.id = cs.event_id
             LEFT JOIN tl_dc_dive_course c ON c.id = cs.course_id
             LEFT JOIN tl_dc_student_exercises se ON se.pid = cs.id
             WHERE cs.published = 1
               AND (c.instructor = ? OR ce.instructor = ? OR se.instructor = ?)
             ORDER BY s.lastname, s.firstname"
        )->execute($user->id, $user->id, $user->id);

        if ($assignments->numRows < 1) {
            return new JsonResponse([]);
        }

        $data = [];
        while ($assignments->next()) {
            $enrollment = DcCourseStudentsModel::findByPk($assignments->id);
            if (!$enrollment) {
                continue;
            }

            $course = DcDiveCourseModel::findByPk($enrollment->course_id);
            $event = DcCourseEventModel::findByPk($enrollment->event_id);
            $student = DcStudentsModel::findByPk($enrollment->pid);

            $enrollmentRow = $enrollment->row();
            foreach (['tstamp', 'registered_on', 'dateBrevet', 'start', 'stop'] as $f) {
                if (isset($enrollmentRow[$f]) && $enrollmentRow[$f] !== '') {
                    $enrollmentRow[$f] = (int)$enrollmentRow[$f];
                }
            }
            foreach (['payed', 'brevet', 'published'] as $f) {
                if (isset($enrollmentRow[$f])) {
                    $enrollmentRow[$f] = (bool)$enrollmentRow[$f];
                }
            }
            if (isset($enrollmentRow['notes'])) {
                $enrollmentRow['notes'] = (string)$enrollmentRow['notes'];
            }

            $studentRow = null;
            if ($student) {
                $studentRow = $student->row();
                foreach (['tstamp', 'dateOfBirth', 'start', 'stop'] as $f) {
                    if (isset($studentRow[$f]) && $studentRow[$f] !== '') {
                        $studentRow[$f] = (int)$studentRow[$f];
                    }
                }
                foreach (['published', 'medical_ok', 'allowLogin'] as $f) {
                    if (isset($studentRow[$f])) {
                        $studentRow[$f] = (bool)$studentRow[$f];
                    }
                }
                if (isset($studentRow['memberGroups'])) {
                    $studentRow['memberGroups'] = array_map('intval', StringUtil::deserialize($studentRow['memberGroups'], true));
                }
                if (isset($studentRow['notes'])) {
                    $studentRow['notes'] = (string)$studentRow['notes'];
                }
            }

            $exercises = DcStudentExercisesModel::findBy('pid', $enrollment->id);
            $exerciseData = [];
            if ($exercises) {
                foreach ($exercises as $exercise) {
                    $exRow = $exercise->row();
                    foreach (['tstamp', 'dateCompleted', 'start', 'stop'] as $f) {
                        if (isset($exRow[$f]) && $exRow[$f] !== '') {
                            $exRow[$f] = (int)$exRow[$f];
                        }
                    }
                    if (isset($exRow['published'])) {
                        $exRow['published'] = (bool)$exRow['published'];
                    }
                    if (isset($exRow['notes'])) {
                        $exRow['notes'] = (string)$exRow['notes'];
                    }

                    // Add exercise title
                    $exerciseModel = DcCourseExercisesModel::findByPk($exercise->exercise_id);
                    $exRow['title'] = $exerciseModel ? html_entity_decode($exerciseModel->title) : 'Unknown Exercise';

                    $exerciseData[] = $exRow;
                }
            }

            $data[] = [
                'id' => (int)$enrollment->id,
                'enrollment_id' => (int)$enrollment->id,
                'enrollment' => $enrollmentRow,
                'course_title' => $course ? $course->title : ($event ? $event->title : 'Unknown Course'),
                'event_title' => $event ? $event->title : null,
                'student' => $studentRow,
                'status' => $enrollment->status,
                'exercises' => $exerciseData
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Update progress (for instructors)
     */
    #[Route('/{exerciseId}', name: 'update', methods: ['PATCH'], requirements: ['exerciseId' => '\d+'])]
    public function updateProgress(int $exerciseId, Request $request): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $exercise = DcStudentExercisesModel::findByPk($exerciseId);
        if (!$exercise) {
            return new JsonResponse(['error' => 'Exercise not found'], 404);
        }

        if (!$this->canManageExercise($user, $exercise)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $content = $this->decodeJsonPayload($request);
        if (null === $content) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // The app may update only progress fields; enrollment and course data stay immutable here.
        if (isset($content['status'])) {
            $exercise->status = (string)$content['status'];
        }
        if (isset($content['dateCompleted'])) {
            $exercise->dateCompleted = (int)$content['dateCompleted'];
        }
        if (isset($content['notes'])) {
            $exercise->notes = (string)$content['notes'];
        }

        // Default to the logged-in instructor so manually completed exercises are attributable.
        $exercise->instructor = (int)($content['instructor'] ?? $user->id);
        $exercise->tstamp = time();

        if (!$exercise->save()) {
            return new JsonResponse(['error' => 'Could not update exercise'], 500);
        }

        return new JsonResponse(['success' => true]);
    }

    private function canManageExercise(FrontendUser $user, DcStudentExercisesModel $exercise): bool
    {
        if ($this->isTrainingManager($user)) {
            return true;
        }

        if ((int)($exercise->instructor ?? 0) === (int)$user->id) {
            return true;
        }

        $enrollment = DcCourseStudentsModel::findByPk($exercise->pid);
        if (!$enrollment) {
            return false;
        }

        // Keep this authorization rule in sync with instructorStudents(): an
        // instructor may manage exercises assigned through the course template,
        // the concrete event, or the exercise itself.
        $course = DcDiveCourseModel::findByPk($enrollment->course_id);
        if ($course && (int)($course->instructor ?? 0) === (int)$user->id) {
            return true;
        }

        $event = DcCourseEventModel::findByPk($enrollment->event_id);
        if ($event && (int)($event->instructor ?? 0) === (int)$user->id) {
            return true;
        }

        return false;
    }

    private function isTrainingManager(FrontendUser $user): bool
    {
        $config = DcConfigModel::findOneBy('published', '1');
        if (null === $config) {
            return false;
        }

        $trainingManagers = StringUtil::deserialize($config->training_manager, true);

        return in_array((int)$user->id, array_map('intval', $trainingManagers), true);
    }
}
