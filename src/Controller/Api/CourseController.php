<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Diversworld\ContaoDiveclubBundle\EventListener\DataContainer\StudentsListener;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcDiveCourseModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/courses', name: 'api_courses_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class CourseController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly StudentsListener $studentsListener,
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

    #[Route('/enroll', name: 'enroll', methods: ['POST'], defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function enroll(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $content = $this->decodeJsonPayload($request);
        if (null === $content || (!isset($content['course_id']) && !isset($content['event_id']))) {
            return new JsonResponse(['error' => 'Invalid JSON or missing course_id or event_id'], 400);
        }

        $target = $this->resolveEnrollmentTarget($content);
        if ($target instanceof JsonResponse) {
            return $target;
        }

        $courseId = $target['courseId'];
        $eventId = $target['eventId'];
        $event = $target['event'];

        if ($registrationError = $this->getRegistrationError($event)) {
            return new JsonResponse(['error' => $registrationError], 400);
        }

        $user = $this->security->getUser();
        $student = $this->resolveStudentForEnrollment(
            $content,
            $user instanceof FrontendUser ? $user : null
        );

        if ($student instanceof JsonResponse) {
            return $student;
        }

        $existing = $this->findExistingEnrollment($student, $eventId);
        if ($existing) {
            return new JsonResponse([
                'error' => 'Already enrolled in this course',
                'id' => (int)$existing->id,
                'enrollment_id' => (int)$existing->id,
                'student_id' => (int)$student->id,
            ], 400);
        }

        $enrollment = $this->createEnrollment($student, $courseId, $eventId);
        if (null === $enrollment) {
            return new JsonResponse(['error' => 'Could not save enrollment'], 500);
        }

        $this->generateDefaultExercises($enrollment, $courseId, $eventId);

        return new JsonResponse([
            'success' => true,
            'id' => (int)$enrollment->id,
            'enrollment_id' => (int)$enrollment->id,
            'student_id' => (int)$student->id,
            'course_id' => $courseId,
            'event_id' => $eventId,
        ], 201);
    }

    private function resolveEnrollmentTarget(array $content): array|JsonResponse
    {
        $courseId = (int)($content['course_id'] ?? 0);
        $eventId = (int)($content['event_id'] ?? 0);

        if ($eventId > 0) {
            $event = DcCourseEventModel::findByPk($eventId);
        } elseif ($courseId > 0) {
            // The course list returns concrete event IDs as "id". Accepting that
            // value as course_id keeps existing app payloads working.
            $event = DcCourseEventModel::findByPk($courseId);
        } else {
            $event = null;
        }

        if (null === $event || !$this->isVisiblePublishedEvent($event)) {
            return new JsonResponse(['error' => 'Für die Anmeldung muss ein konkreter Kurs ausgewählt werden.'], 400);
        }

        $eventId = (int)$event->id;
        $courseId = (int)$event->course_id;
        $course = DcDiveCourseModel::findByPk($courseId);

        if (null === $course || !$course->published) {
            return new JsonResponse(['error' => 'Course not found'], 404);
        }

        return [
            'courseId' => $courseId,
            'eventId' => $eventId,
            'event' => $event,
        ];
    }

    private function getRegistrationError(DcCourseEventModel $event): ?string
    {
        $now = time();
        $dateStart = (int)($event->dateStart ?? 0);
        $registrationStart = (int)($event->registration_start ?? 0);
        $registrationEnd = (int)($event->registration_end ?? 0);

        if ($dateStart > 0 && $now >= $dateStart) {
            return 'Eine Anmeldung ist nicht mehr möglich, weil der Kurs bereits gestartet ist.';
        }

        if ($registrationStart < 1 || $registrationEnd < 1) {
            return 'Für diesen Kurs ist aktuell kein Anmeldezeitraum hinterlegt.';
        }

        if ($registrationStart > 0 && $now < $registrationStart) {
            return 'Die Anmeldung ist noch nicht geöffnet.';
        }

        if ($registrationEnd > 0 && $now > $registrationEnd) {
            return 'Der Anmeldezeitraum ist abgelaufen.';
        }

        return null;
    }

    private function resolveStudentForEnrollment(array $content, ?FrontendUser $user): DcStudentsModel|JsonResponse
    {
        $forceGuest = ($content['mode'] ?? '') === 'guest';

        if (null !== $user && !$forceGuest) {
            return $this->resolveMemberStudent($user);
        }

        return $this->resolveGuestStudent($content, $user);
    }

    private function resolveMemberStudent(FrontendUser $user): DcStudentsModel|JsonResponse
    {
        $student = DcStudentsModel::findOneBy('memberId', $user->id);
        if ($student) {
            return $student;
        }

        if (!empty($user->email)) {
            $student = DcStudentsModel::findOneByEmail((string)$user->email);

            if ($student) {
                if ((int)$student->memberId === 0) {
                    $student->memberId = (int)$user->id;
                    $student->save();
                }

                return $student;
            }
        }

        $student = new DcStudentsModel();
        $student->tstamp = time();
        $student->memberId = (int)$user->id;
        $student->gender = (string)$user->gender;
        $student->firstname = (string)$user->firstname;
        $student->lastname = (string)$user->lastname;
        $student->street = (string)$user->street;
        $student->postal = (string)$user->postal;
        $student->city = (string)$user->city;
        $student->email = (string)$user->email;
        $student->phone = (string)$user->phone;
        $student->mobile = (string)$user->mobile;
        $student->dateOfBirth = $this->normalizeDateOfBirth($user->dateOfBirth ?? '');
        $student->published = '1';

        if (!$student->save()) {
            return new JsonResponse(['error' => 'Could not create student profile'], 500);
        }

        return $student;
    }

    private function resolveGuestStudent(array $content, ?FrontendUser $user): DcStudentsModel|JsonResponse
    {
        $studentData = $this->extractStudentPayload($content);
        $errors = $this->validateGuestStudentPayload($studentData);

        if ($errors !== []) {
            return new JsonResponse(['error' => 'Invalid student data', 'details' => $errors], 400);
        }

        $email = $this->getPayloadString($studentData, 'email');
        $student = DcStudentsModel::findOneByEmail($email);

        if ($student) {
            if (null !== $user && (int)$student->memberId === 0) {
                $student->memberId = (int)$user->id;
                $student->save();
            }

            return $student;
        }

        $student = new DcStudentsModel();
        $student->tstamp = time();
        $student->gender = $this->getPayloadString($studentData, 'gender');
        $student->firstname = $this->getPayloadString($studentData, 'firstname', 'first_name', 'firstName');
        $student->lastname = $this->getPayloadString($studentData, 'lastname', 'last_name', 'lastName');
        $student->street = $this->getPayloadString($studentData, 'street');
        $student->postal = $this->getPayloadString($studentData, 'postal', 'zip', 'zipCode');
        $student->city = $this->getPayloadString($studentData, 'city');
        $student->state = $this->getPayloadString($studentData, 'state');
        $student->country = $this->getPayloadString($studentData, 'country');
        $student->language = $this->getPayloadString($studentData, 'language');
        $student->email = $email;
        $student->phone = $this->getPayloadString($studentData, 'phone');
        $student->mobile = $this->getPayloadString($studentData, 'mobile');
        $student->dateOfBirth = $this->normalizeDateOfBirth($studentData['dateOfBirth'] ?? $studentData['birthdate'] ?? '');
        $student->memberId = null !== $user ? (int)$user->id : 0;
        $student->published = '1';

        if (!$student->save()) {
            return new JsonResponse(['error' => 'Could not create student profile'], 500);
        }

        return $student;
    }

    private function extractStudentPayload(array $content): array
    {
        $studentData = isset($content['student']) && is_array($content['student']) ? $content['student'] : [];

        return array_merge($content, $studentData);
    }

    private function validateGuestStudentPayload(array $studentData): array
    {
        $errors = [];

        if ($this->getPayloadString($studentData, 'firstname', 'first_name', 'firstName') === '') {
            $errors['firstname'] = 'Firstname is required';
        }

        if ($this->getPayloadString($studentData, 'lastname', 'last_name', 'lastName') === '') {
            $errors['lastname'] = 'Lastname is required';
        }

        if ($this->getPayloadString($studentData, 'street') === '') {
            $errors['street'] = 'Street is required';
        }

        if ($this->getPayloadString($studentData, 'postal', 'zip', 'zipCode') === '') {
            $errors['postal'] = 'Postal code is required';
        }

        if ($this->getPayloadString($studentData, 'city') === '') {
            $errors['city'] = 'City is required';
        }

        $email = $this->getPayloadString($studentData, 'email');
        if ($email === '' || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required';
        }

        if (!$this->isTruthy($studentData['privacy'] ?? $studentData['privacy_accepted'] ?? $studentData['data_privacy'] ?? false)) {
            $errors['privacy'] = 'Privacy consent is required';
        }

        return $errors;
    }

    private function findExistingEnrollment(DcStudentsModel $student, int $eventId): ?DcCourseStudentsModel
    {
        return DcCourseStudentsModel::findOneBy(
            ['pid=?', 'event_id=?'],
            [$student->id, $eventId]
        );
    }

    private function createEnrollment(DcStudentsModel $student, int $courseId, int $eventId): ?DcCourseStudentsModel
    {
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
            return null;
        }

        return $enrollment;
    }

    private function generateDefaultExercises(DcCourseStudentsModel $enrollment, int $courseId, int $eventId): void
    {
        // Contao model saves do not trigger DCA callbacks. The Diveclub bundle
        // uses this callback to create the student's default exercise records,
        // so the API calls it explicitly after creating the assignment.
        $dataContainer = new class extends DataContainer {
            public function __construct()
            {
            }

            public function save($varValue)
            {
            }

            public function isChanged()
            {
                return false;
            }

            public function getPalette()
            {
                return '';
            }
        };
        $dataContainer->id = (int)$enrollment->id;
        $dataContainer->activeRecord = (object)[
            'id' => (int)$enrollment->id,
            'course_id' => $courseId,
            'event_id' => $eventId,
        ];

        $this->studentsListener->onCourseStudentSubmit($dataContainer);
    }

    private function getPayloadString(array $payload, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return trim((string)$payload[$key]);
            }
        }

        return '';
    }

    private function normalizeDateOfBirth(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        $date = trim((string)$value);
        if ($date === '') {
            return '';
        }

        if (ctype_digit($date)) {
            return (string)(int)$date;
        }

        $timestamp = strtotime($date);

        return false !== $timestamp ? (string)$timestamp : '';
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
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
