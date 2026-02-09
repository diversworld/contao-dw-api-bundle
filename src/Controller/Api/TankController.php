<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcTanksModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tanks', name: 'api_tanks_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class TankController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $models = DcTanksModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();

            // Convert date fields to timestamp
            foreach (['tstamp', 'lastCheckDate', 'nextCheckDate', 'start', 'stop'] as $field) {
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
        $model = DcTanksModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank not found'], 404);
        }

        $row = $model->row();

        // Convert date fields to timestamp
        foreach (['tstamp', 'lastCheckDate', 'nextCheckDate', 'start', 'stop'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        return new JsonResponse($row);
    }
}
