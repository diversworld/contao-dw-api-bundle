<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcRegulatorsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/regulators', name: 'api_regulators_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class RegulatorController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $models = DcRegulatorsModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();

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
        $model = DcRegulatorsModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Regulator not found'], 404);
        }

        $row = $model->row();

        // Convert date fields to timestamp
        foreach (['tstamp', 'start', 'stop'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        return new JsonResponse($row);
    }
}
