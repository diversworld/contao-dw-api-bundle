<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/events', name: 'api_events_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class EventController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $models = DcCourseEventModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $data[] = $model->row();
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $model = DcCourseEventModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        $data = $model->row();
        $data['currentParticipants'] = (int)DcCourseStudentsModel::countBy(
            ['event_id=?', 'status=?'],
            [$model->id, 'registered']
        );

        return new JsonResponse($data);
    }
}
