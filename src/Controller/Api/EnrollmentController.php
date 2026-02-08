<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcDiveCourseModel;
use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/enrollments', name: 'api_enrollments_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class EnrollmentController extends AbstractController
{
    public function __construct(
        private readonly Security $security
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Find student record for the member
        $student = DcStudentsModel::findOneBy('memberId', $user->id);
        if (!$student) {
            return new JsonResponse([]);
        }

        $enrollments = DcCourseStudentsModel::findBy('pid', $student->id);

        $result = [];
        if ($enrollments !== null) {
            /** @var DcCourseStudentsModel $enrollment */
            foreach ($enrollments as $enrollment) {
                $event = DcCourseEventModel::findByPk($enrollment->event_id);

                if ($event) {
                    $result[] = [
                        'id' => (int)$enrollment->id,
                        'title' => html_entity_decode($event->title),
                        'event_id' => (int)$event->id,
                        'reservation_status' => $enrollment->status,
                        'dateStart' => (int)$event->dateStart,
                    ];
                }
            }
        }

        return new JsonResponse($result);
    }
}
