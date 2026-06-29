<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FilesModel;
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
        // Publicly accessible list of real course events, not course templates.
        $this->framework->initialize();
        $models = $this->findCurrentAndUpcomingCourseEvents();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $data[] = $this->buildCourseEventPayload($model);
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        // Publicly accessible course event detail.
        $this->framework->initialize();
        $model = DcCourseEventModel::findByPk($id);

        if (null === $model || !$this->isVisiblePublishedEvent($model)) {
            return new JsonResponse(['error' => 'Course not found'], 404);
        }

        return new JsonResponse($this->buildCourseEventPayload($model));
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

        if ($eventId < 1) {
            $event = DcCourseEventModel::findByPk($courseId);

            if (null !== $event && $this->isVisiblePublishedEvent($event) && (int)$event->course_id > 0) {
                $eventId = (int)$event->id;
                $courseId = (int)$event->course_id;
            }
        }

        $course = DcDiveCourseModel::findByPk($courseId);
        if (null === $course || !$course->published) {
            return new JsonResponse(['error' => 'Course not found'], 404);
        }

        if ($eventId > 0) {
            $event = DcCourseEventModel::findByPk($eventId);

            if (null === $event || !$this->isVisiblePublishedEvent($event)) {
                return new JsonResponse(['error' => 'Event not found'], 404);
            }

            if ((int)$event->course_id !== $courseId) {
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

    private function findCurrentAndUpcomingCourseEvents()
    {
        $now = time();
        $todayStart = strtotime('today', $now) ?: $now;

        // /api/courses exposes concrete course events from tl_dc_course_event.
        // dateEnd is the business end date: once today's date is greater than
        // dateEnd the course is over and must disappear from the public list.
        // Without a dateEnd the course is treated as still running.
        return DcCourseEventModel::findBy(
            [
                'published=?',
                '(start IS NULL OR start=? OR start<=?)',
                '(stop IS NULL OR stop=? OR stop>?)',
                '(dateEnd IS NULL OR dateEnd<=? OR dateEnd>=?)',
            ],
            ['1', '', (string)$now, '', (string)$now, 0, $todayStart],
            ['order' => 'dateStart ASC, sorting ASC, title ASC']
        );
    }

    private function isVisiblePublishedEvent(DcCourseEventModel $event): bool
    {
        if (!$event->published) {
            return false;
        }

        $now = time();
        if (!empty($event->start) && (int)$event->start > $now) {
            return false;
        }

        if (!empty($event->stop) && (int)$event->stop <= $now) {
            return false;
        }

        $todayStart = strtotime('today', $now) ?: $now;
        $dateEnd = (int)($event->dateEnd ?? 0);

        if ($dateEnd > 0) {
            return $dateEnd >= $todayStart;
        }

        // If no end date is maintained, the event is still running.
        return true;
    }

    private function buildCourseEventPayload(DcCourseEventModel $event): array
    {
        $row = $event->row();
        $courseId = (int)($row['course_id'] ?? 0);
        $course = $courseId > 0 ? DcDiveCourseModel::findByPk($courseId) : null;
        $courseRow = $course ? $course->row() : [];

        foreach (['description', 'price'] as $field) {
            if (($row[$field] ?? '') === '' && isset($courseRow[$field]) && $courseRow[$field] !== '') {
                $row[$field] = $courseRow[$field];
            }
        }

        if ((int)($row['max_participants'] ?? 0) === 0 && isset($courseRow['max_participants'])) {
            $row['max_participants'] = $courseRow['max_participants'];
        }

        $row['id'] = (int)$event->id;
        $row['event_id'] = (int)$event->id;
        $row['course_id'] = $courseId;
        $row['course_title'] = $course ? (string)$course->title : '';
        $row['course_type'] = $courseRow['course_type'] ?? '';
        $row['category'] = $courseRow['category'] ?? '';
        $row['requirements'] = $courseRow['requirements'] ?? '';

        $row['image'] = $this->resolveImagePath($event->singleSRC ?: ($course?->singleSRC ?? null));

        if (isset($row['singleSRC']) && $row['singleSRC']) {
            $row['singleSRC'] = StringUtil::binToUuid($row['singleSRC']);
        } elseif ($course && $course->singleSRC) {
            $row['singleSRC'] = StringUtil::binToUuid($course->singleSRC);
        }

        foreach (['id', 'sorting', 'tstamp', 'dateStart', 'dateEnd', 'registration_start', 'registration_end', 'course_id', 'event_id', 'instructor', 'max_participants'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        foreach (['start', 'stop'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        $row['currentParticipants'] = (int)DcCourseStudentsModel::countBy(
            ['event_id=?', 'published=?'],
            [(int)$event->id, '1']
        );

        return $row;
    }

    private function resolveImagePath(mixed $uuid): string
    {
        if (!$uuid) {
            return '';
        }

        $file = FilesModel::findByUuid($uuid);

        return $file ? (string)$file->path : '';
    }
}
