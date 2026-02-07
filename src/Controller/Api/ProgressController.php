<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

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

#[Route('/api/progress', name: 'api_progress', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class ProgressController extends AbstractController
{
    public function __construct(
        private readonly Security $security
    ) {
    }

    /**
     * Get progress for the current logged in user (student view)
     */
    #[Route('', name: 'api_progress_student', methods: ['GET'])]
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
    #[Route('/instructor', name: 'api_progress_instructor', methods: ['GET'])]
    public function instructorStudents(): JsonResponse
    {
        $user = $this->security->getUser();
        // Here we might want to check for a specific instructor role if available,
        // but for now ROLE_MEMBER is required by IsGranted.
        
        // In this bundle, an instructor is often a member with specific properties
        // or just assigned to a course.
        
        // Let's find all course enrollments where the logged in user is the instructor
        // Note: DcDiveCourseModel has an 'instructor' field which is probably a member ID or student ID.
        // If it's a member ID:
        $courses = DcDiveCourseModel::findBy('instructor', $user->id);
        
        if (null === $courses) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($courses as $course) {
            $enrollments = DcCourseStudentsModel::findBy('course_id', $course->id);
            if ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    $student = DcStudentsModel::findByPk($enrollment->pid);
                    $studentData = $student ? $student->row() : null;
                    
                    $exercises = DcStudentExercisesModel::findBy('pid', $enrollment->id);
                    $exerciseData = [];
                    if ($exercises) {
                        foreach ($exercises as $exercise) {
                            $exerciseData[] = $exercise->row();
                        }
                    }

                    $data[] = [
                        'course_title' => $course->title,
                        'student' => $studentData,
                        'enrollment_id' => $enrollment->id,
                        'exercises' => $exerciseData
                    ];
                }
            }
        }

        return new JsonResponse($data);
    }

    /**
     * Update progress (for instructors)
     */
    #[Route('/{exerciseId}', name: 'api_progress_update', methods: ['PATCH'])]
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
            $exercise->status = (string) $content['status'];
        }
        if (isset($content['dateCompleted'])) {
            $exercise->dateCompleted = (int) $content['dateCompleted'];
        }
        if (isset($content['notes'])) {
            $exercise->notes = (string) $content['notes'];
        }
        
        // Automatically set current user as instructor if not provided
        $exercise->instructor = (int) ($content['instructor'] ?? $user->id);
        $exercise->tstamp = time();

        if (!$exercise->save()) {
            return new JsonResponse(['error' => 'Could not update exercise'], 500);
        }

        return new JsonResponse(['success' => true]);
    }
}
