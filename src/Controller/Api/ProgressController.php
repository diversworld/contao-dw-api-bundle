<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FrontendUser;
use Contao\StringUtil;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentExercisesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcDiveCourseModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/progress', name: 'api_progress_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class ProgressController extends AbstractController
{
    public function __construct(
        private readonly Security $security
    )
    {
    }

    /**
     * Get progress for the current logged in user (student view)
     */
    #[Route('', name: 'student', methods: ['GET'])]
    public function studentProgress(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
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
            $item = [
                'enrollment' => $enrollment->row(),
                'course' => $course ? $course->row() : null,
                'exercises' => []
            ];

            $exercises = DcStudentExercisesModel::findBy('pid', $enrollment->id);
            if ($exercises) {
                foreach ($exercises as $exercise) {
                    $item['exercises'][] = $exercise->row();
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
        $user = $this->security->getUser();
        if (!$user) {
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

            $exercises = DcStudentExercisesModel::findBy('pid', $enrollment->id);
            $exerciseData = [];
            if ($exercises) {
                foreach ($exercises as $exercise) {
                    $exerciseData[] = $exercise->row();
                }
            }

            $data[] = [
                'enrollment_id' => (int)$enrollment->id,
                'course_title' => $course ? $course->title : ($event ? $event->title : 'Unknown Course'),
                'event_title' => $event ? $event->title : null,
                'student' => $student ? $student->row() : null,
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
        // Simple instructor check: Is logged in?
        $user = $this->security->getUser();

        $exercise = DcStudentExercisesModel::findByPk($exerciseId);
        if (!$exercise) {
            return new JsonResponse(['error' => 'Exercise not found'], 404);
        }

        $content = json_decode($request->getContent(), true);
        if (!$content) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Only allow updating status, dateCompleted, instructor, notes
        if (isset($content['status'])) {
            $exercise->status = (string)$content['status'];
        }
        if (isset($content['dateCompleted'])) {
            $exercise->dateCompleted = (int)$content['dateCompleted'];
        }
        if (isset($content['notes'])) {
            $exercise->notes = (string)$content['notes'];
        }

        // Automatically set current user as instructor if not provided
        $exercise->instructor = (int)($content['instructor'] ?? $user->id);
        $exercise->tstamp = time();

        if (!$exercise->save()) {
            return new JsonResponse(['error' => 'Could not update exercise'], 500);
        }

        return new JsonResponse(['success' => true]);
    }
}
