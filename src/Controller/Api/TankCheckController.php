<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcCheckProposalModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCheckArticlesModel;
use Contao\CalendarEventsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tank-checks', name: 'api_tank_checks', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class TankCheckController extends AbstractController
{
    #[Route('', name: 'api_tank_checks_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $models = DcCheckProposalModel::findBy(['published=?'], [1], ['order' => 'proposalDate DESC']);

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $item = $model->row();

            // Verknüpftes Event laden für genaues Datum
            if ($model->checkId) {
                $event = CalendarEventsModel::findByPk($model->checkId);
                if ($event) {
                    $item['event_date'] = $event->startDate;
                }
            }

            $data[] = $item;
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_tank_checks_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $model = DcCheckProposalModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank check proposal not found'], 404);
        }

        $data = $model->row();

        // Artikel laden
        $articles = DcCheckArticlesModel::findBy('pid', $model->id);
        $data['articles'] = [];

        if ($articles) {
            foreach ($articles as $article) {
                $data['articles'][] = $article->row();
            }
        }

        return new JsonResponse($data);
    }
}
