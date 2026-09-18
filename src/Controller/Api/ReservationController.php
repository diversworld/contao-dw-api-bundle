<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcReservationItemsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcReservationModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/reservations', name: 'api_reservations_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class ReservationController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security        $security,
        private readonly Connection      $db,
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $models = DcReservationModel::findBy(
            ['member_id=?'],
            [(int)$user->id]
        );

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();
            foreach (['tstamp', 'reserved_at', 'picked_up_at', 'returned_at', 'start', 'stop'] as $f) {
                if (isset($row[$f]) && $row[$f] !== '') {
                    $row[$f] = (int)$row[$f];
                }
            }
            $data[] = $row;
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $model = DcReservationModel::findOneBy(
            ['id=?', 'member_id=?'],
            [$id, (int)$user->id]
        );

        if (null === $model) {
            return new JsonResponse(['error' => 'Reservation not found'], 404);
        }

        $data = $model->row();
        foreach (['tstamp', 'reserved_at', 'picked_up_at', 'returned_at', 'start', 'stop'] as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $data[$f] = (int)$data[$f];
            }
        }

        // Items laden
        $items = DcReservationItemsModel::findByPid($model->id);
        $data['items'] = [];

        if ($items) {
            foreach ($items as $item) {
                $row = $item->row();
                foreach (['tstamp', 'reserved_at', 'picked_up_at', 'returned_at', 'start', 'stop', 'created_at', 'updated_at'] as $f) {
                    if (isset($row[$f]) && $row[$f] !== '') {
                        $row[$f] = (int)$row[$f];
                    }
                }
                $data['items'][] = $row;
            }
        }

        return new JsonResponse($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $content = $this->decodeJsonPayload($request);

        if (null === $content) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $userId = (int)$user->id;
        $reservedFor = (int)($content['reservedFor'] ?? $userId);
        $eventId = (int)($content['event_id'] ?? 0);

        // Generate title and alias in the same shape as the backend DCA so
        // reservations created by the app stay recognizable in Contao.
        $formattedMemberId = str_pad((string)$userId, 3, '0', STR_PAD_LEFT);
        $currentDate = date('dmHi');
        $currentYear = date('Y');
        $title = $currentYear . '-' . $formattedMemberId . '-' . $currentDate;
        $alias = 'id-' . $title;

        $reservation = new DcReservationModel();
        $reservation->tstamp = time();
        $reservation->setRow([
            'member_id' => $userId,
            'reservedFor' => $reservedFor,
            'event_id' => $eventId,
            'title' => $title,
            'alias' => $alias,
            'reserved_at' => (string)time(),
            'reservation_status' => 'reserved',
            'published' => '1',
            'asset_type' => (string)($content['asset_type'] ?? 'multiple'),
        ]);

        if (!$reservation->save()) {
            return new JsonResponse(['error' => 'Could not save reservation'], 500);
        }

        // Reservation items are stored in a child table; only accept arrays so
        // scalar JSON values cannot create incomplete rows.
        if (isset($content['items']) && is_array($content['items'])) {
            foreach ($content['items'] as $itemData) {
                if (!is_array($itemData)) {
                    continue;
                }

                $this->db->insert('tl_dc_reservation_items', [
                    'pid' => $reservation->id,
                    'tstamp' => time(),
                    'item_id' => (int)($itemData['item_id'] ?? 0),
                    'item_type' => $itemData['item_type'] ?? '',
                    'types' => $itemData['types'] ?? '',
                    'sub_type' => $itemData['sub_type'] ?? '',
                    'reserved_at' => (string)time(),
                    'reservation_status' => 'reserved',
                    'published' => '1',
                    'notes' => $itemData['notes'] ?? '',
                    'created_at' => time(),
                    'updated_at' => time()
                ]);
            }
        }

        return new JsonResponse(['success' => true, 'id' => $reservation->id]);
    }

}
