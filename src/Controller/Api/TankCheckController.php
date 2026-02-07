<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcCheckProposalModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCheckArticlesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCheckBookingModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCheckOrderModel;
use Contao\CalendarEventsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tank-checks', name: 'api_tank_checks', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class TankCheckController extends AbstractController
{
    public function __construct(
        private readonly Security $security
    ) {
    }

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

    #[Route('/book', name: 'api_tank_checks_book', methods: ['POST'])]
    #[IsGranted('ROLE_MEMBER')]
    public function book(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        $content = json_decode($request->getContent(), true);

        if (!$content || !isset($content['proposal_id'], $content['items'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing fields'], 400);
        }

        $proposalId = (int) $content['proposal_id'];
        $proposal = DcCheckProposalModel::findByPk($proposalId);

        if (!$proposal) {
            return new JsonResponse(['error' => 'Proposal not found'], 404);
        }

        // 1. Create Booking (tl_dc_check_booking)
        $booking = new DcCheckBookingModel();
        $booking->tstamp = time();
        $booking->pid = $proposalId;
        $booking->bookingDate = time();
        $booking->bookingNumber = 'B-' . date('Ymd-His') . '-' . $user->id;
        $booking->status = 'ordered';
        $booking->memberId = $user->id;
        $booking->firstname = $user->firstname;
        $booking->lastname = $user->lastname;
        $booking->email = $user->email;
        $booking->phone = $user->phone;

        if (!$booking->save()) {
            return new JsonResponse(['error' => 'Could not create booking'], 500);
        }

        // 2. Create Orders for each item (tl_dc_check_order)
        $totalPrice = 0;
        foreach ($content['items'] as $item) {
            $order = new DcCheckOrderModel();
            $order->tstamp = time();
            $order->pid = $booking->id;
            $order->bookingId = $booking->bookingNumber;
            $order->serialNumber = $item['serialNumber'] ?? '';
            $order->manufacturer = $item['manufacturer'] ?? '';
            $order->bazNumber = $item['bazNumber'] ?? '';
            $order->size = $item['size'] ?? '';
            $order->o2clean = (bool) ($item['o2clean'] ?? false);
            $order->status = 'ordered';
            
            // Articles as blob/serialized array
            if (isset($item['articles']) && is_array($item['articles'])) {
                $order->selectedArticles = serialize($item['articles']);
            }

            // Calculate price if articles provided
            $itemPrice = 0;
            if (isset($item['articles']) && is_array($item['articles'])) {
                foreach ($item['articles'] as $articleId) {
                    $art = DcCheckArticlesModel::findByPk($articleId);
                    if ($art) {
                        $itemPrice += (float) $art->price;
                    }
                }
            }
            $order->totalPrice = $itemPrice;
            $totalPrice += $itemPrice;

            $order->save();
        }

        // Update total price in booking
        $booking->totalPrice = $totalPrice;
        $booking->save();

        return new JsonResponse(['success' => true, 'booking_number' => $booking->bookingNumber]);
    }
}
