<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcTanksModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tanks', name: 'api_tanks_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class TankController extends AbstractController
{
    public function __construct(
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->getFramework()->initialize();
        $models = DcTanksModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();

            // Convert date fields to timestamp
            foreach (['tstamp', 'lastCheckDate', 'nextCheckDate', 'start', 'stop', 'lastOrder'] as $field) {
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
        $this->getFramework()->initialize();
        $model = DcTanksModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank not found'], 404);
        }

        $row = $model->row();

        // Convert date fields to timestamp
        foreach (['tstamp', 'lastCheckDate', 'nextCheckDate', 'start', 'stop', 'lastOrder'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        return new JsonResponse($row);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = \Contao\System::getContainer()->get(ContaoFramework::class);
        }

        return $this->framework;
    }
}
