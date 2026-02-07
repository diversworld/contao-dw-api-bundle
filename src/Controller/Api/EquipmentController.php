<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcEquipmentModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/equipment', name: 'api_equipment', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class EquipmentController extends AbstractController
{
    #[Route('', name: 'api_equipment_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $models = DcEquipmentModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $data[] = $model->row();
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_equipment_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $model = DcEquipmentModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Equipment not found'], 404);
        }

        return new JsonResponse($model->row());
    }
}
