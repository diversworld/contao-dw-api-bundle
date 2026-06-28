<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcRegulatorsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/regulators', name: 'api_regulators_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class RegulatorController
{
    public function __construct(
        private readonly ContaoFramework $framework,
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->framework->initialize();
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

            if (isset($row['rentalFee'])) {
                $row['rentalFee'] = (float)$row['rentalFee'];
            }

            $data[] = $row;
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $this->framework->initialize();
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

        if (isset($row['rentalFee'])) {
            $row['rentalFee'] = (float)$row['rentalFee'];
        }

        return new JsonResponse($row);
    }

}
