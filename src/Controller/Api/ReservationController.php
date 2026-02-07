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

        $data = $model->row();

        // Items laden
        $items = \Diversworld\ContaoDiveclubBundle\Model\DcReservationItemsModel::findBy('pid', $model->id);
        $data['items'] = [];

        if ($items) {
            foreach ($items as $item) {
                $data['items'][] = $item->row();
            }
        }

        return new JsonResponse($data);
    }

    #[Route('', name: 'api_reservations_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $content = json_decode($request->getContent(), true);

        if (!$content) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $userId = (int)($content['member_id'] ?? 0);
        $reservedFor = (int)($content['reservedFor'] ?? $userId);

        if (!$userId) {
            return new JsonResponse(['error' => 'Missing member_id'], 400);
        }

        $reservation = new DcReservationModel();
        $reservation->tstamp = time();
        $reservation->member_id = $userId;
        $reservation->reservedFor = $reservedFor;
        $reservation->title = 'API-' . date('Y-m-d-H-i') . '-' . $userId;
        $reservation->reserved_at = (string)time();
        $reservation->reservation_status = 'reserved';
        $reservation->published = '1';
        $reservation->asset_type = $content['asset_type'] ?? 'multiple';

        if (!$reservation->save()) {
            return new JsonResponse(['error' => 'Could not save reservation'], 500);
        }

        // Items verarbeiten
        if (isset($content['items']) && is_array($content['items'])) {
            $db = \Contao\System::getContainer()->get('database_connection');
            foreach ($content['items'] as $itemData) {
                $db->insert('tl_dc_reservation_items', [
                    'pid' => $reservation->id,
                    'tstamp' => time(),
                    'item_id' => (int)($itemData['item_id'] ?? 0),
                    'item_type' => $itemData['item_type'] ?? '',
                    'reserved_at' => (string)time(),
                    'reservation_status' => 'reserved',
                    'published' => '1'
                ]);
            }
        }

        return new JsonResponse(['success' => true, 'id' => $reservation->id]);
    }
}
