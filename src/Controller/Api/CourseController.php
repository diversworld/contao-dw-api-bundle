<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcDiveCourseModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/courses', name: 'api_courses_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class CourseController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // Publicly accessible course list
        $this->framework->initialize();
        $models = DcDiveCourseModel::findPublished();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();
            if (isset($row['singleSRC']) && $row['singleSRC']) {
                $row['singleSRC'] = StringUtil::binToUuid($row['singleSRC']);
            }

            // Convert date fields to timestamp
            foreach (['tstamp', 'start', 'stop'] as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $row[$field] = (int)$row[$field];
                }
            }

            $data[] = $row;
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        // Publicly accessible course detail
        $this->framework->initialize();
        $model = DcDiveCourseModel::findByPk($id);

        if (null === $model || !$model->published) {
            return new JsonResponse(['error' => 'Course not found'], 404);
        }

        $row = $model->row();
        if (isset($row['singleSRC']) && $row['singleSRC']) {
            $row['singleSRC'] = StringUtil::binToUuid($row['singleSRC']);
        }

        // Convert date fields to timestamp
        foreach (['tstamp', 'start', 'stop'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        return new JsonResponse($row);
    }

    #[Route('/enroll', name: 'enroll', methods: ['POST'])]
    #[IsGranted('ROLE_MEMBER')]
    public function enroll(Request $request): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();
        if (!$user instanceof FrontendUser) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $content = $this->decodeJsonPayload($request);
        if (null === $content || !isset($content['course_id'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing course_id'], 400);
        }

        $courseId = (int)$content['course_id'];
        $eventId = (int)($content['event_id'] ?? 0);

        $course = DcDiveCourseModel::findByPk($courseId);
        if (null === $course || !$course->published) {
            return new JsonResponse(['error' => 'Course not found'], 404);
        }

        if ($eventId > 0) {
            $event = DcCourseEventModel::findByPk($eventId);
            if (null === $event || (int)$event->course_id !== $courseId) {
                return new JsonResponse(['error' => 'Event does not belong to this course'], 400);
            }
        }

        // Every course enrollment is linked to a student record. Create the
        // student profile lazily from the logged-in member when it does not yet exist.
        $student = DcStudentsModel::findOneBy('memberId', $user->id);

        if (!$student) {
            $student = new DcStudentsModel();
            $student->tstamp = time();
            $student->memberId = $user->id;
            $student->firstname = $user->firstname;
            $student->lastname = $user->lastname;
            $student->email = $user->email;

            if (!$student->save()) {
                return new JsonResponse(['error' => 'Could not create student profile'], 500);
            }
        }

        $existing = $eventId > 0
            ? DcCourseStudentsModel::findOneBy(['pid=?', 'course_id=?', 'event_id=?'], [$student->id, $courseId, $eventId])
            : DcCourseStudentsModel::findOneBy(['pid=?', 'course_id=?'], [$student->id, $courseId]);

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
        $enrollment->payed = false;
        $enrollment->brevet = false;
        $enrollment->published = true;

        if (!$enrollment->save()) {
            return new JsonResponse(['error' => 'Could not save enrollment'], 500);
        }

        return new JsonResponse(['success' => true, 'id' => $enrollment->id]);
    }
}
