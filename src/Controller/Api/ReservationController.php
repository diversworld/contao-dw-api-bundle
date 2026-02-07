<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcReservationModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reservations', name: 'api_reservations', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class ReservationController extends AbstractController
{
    #[Route('', name: 'api_reservations_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $models = DcReservationModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $data[] = $model->row();
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_reservations_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $model = DcReservationModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Reservation not found'], 404);
        }

        return new JsonResponse($model->row());
    }
}
