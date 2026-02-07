<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcDiveCourseModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/courses', name: 'api_courses', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class CourseController extends AbstractController
{
    public function __construct(
        private readonly Security $security
    ) {
    }

    #[Route('', name: 'api_courses_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $models = DcDiveCourseModel::findAll(['column' => 'published', 'value' => '1']);

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $data[] = $model->row();
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_courses_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $model = DcDiveCourseModel::findByPk($id);

        if (null === $model || !$model->published) {
            return new JsonResponse(['error' => 'Course not found'], 404);
        }

        return new JsonResponse($model->row());
    }

    #[Route('/enroll', name: 'api_courses_enroll', methods: ['POST'])]
    public function enroll(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $content = json_decode($request->getContent(), true);
        if (!$content || !isset($content['course_id'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing course_id'], 400);
        }

        $courseId = (int) $content['course_id'];
        $eventId = (int) ($content['event_id'] ?? 0);

        // Find student record for the member
        $student = DcStudentsModel::findOneBy('memberId', $user->id);
        if (!$student) {
            return new JsonResponse(['error' => 'No student profile found for this user'], 404);
        }

        // Check if already enrolled
        $existing = DcCourseStudentsModel::findBy(['pid=?', 'course_id=?'], [$student->id, $courseId]);
        if ($existing) {
            return new JsonResponse(['error' => 'Already enrolled in this course'], 400);
        }

        $enrollment = new DcCourseStudentsModel();
        $enrollment->tstamp = time();
        $enrollment->pid = $student->id;
        $enrollment->course_id = $courseId;
        $enrollment->event_id = $eventId;
        $enrollment->status = 'registered';
        $enrollment->registered_on = time();
        $enrollment->published = '1';

        if (!$enrollment->save()) {
            return new JsonResponse(['error' => 'Could not save enrollment'], 500);
        }

        return new JsonResponse(['success' => true, 'id' => $enrollment->id]);
    }
}
