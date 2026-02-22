<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcCheckProposalModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCheckArticlesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCheckBookingModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCheckOrderModel;
use Diversworld\ContaoDiveclubBundle\Helper\TankCheckHelper;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tank-checks', name: 'api_tank_checks_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class TankCheckController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->getFramework()->initialize();
        $models = DcCheckProposalModel::findBy(['published=?'], [1], ['order' => 'proposalDate DESC']);

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $item = $model->row();

            // Convert date fields to timestamp
            foreach (['tstamp', 'proposalDate', 'start', 'stop'] as $field) {
                if (isset($item[$field]) && $item[$field] !== '') {
                    $item[$field] = (int)$item[$field];
                }
            }

            // Normalisierte Preis-Felder
            if (isset($item['rentalFee'])) {
                $item['rentalFee'] = (float)$item['rentalFee'];
            }

            // Verknüpftes Event laden für genaues Datum
            if ($model->checkId) {
                $event = CalendarEventsModel::findByPk($model->checkId);
                if ($event) {
                    $item['event_date'] = (int)$event->startDate;
                }
            }

            $data[] = $item;
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        $model = DcCheckProposalModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank check proposal not found'], 404);
        }

        $data = $model->row();

        // Convert date fields to timestamp
        foreach (['tstamp', 'proposalDate', 'start', 'stop'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = (int)$data[$field];
            }
        }

        if (isset($data['rentalFee'])) {
            $data['rentalFee'] = (float)$data['rentalFee'];
        }

        // Artikel laden
        $articles = DcCheckArticlesModel::findBy('pid', $model->id);
        $data['articles'] = [];

        if ($articles) {
            foreach ($articles as $article) {
                $row = $article->row();
                if (isset($row['articlePriceBrutto'])) {
                    $row['price'] = (float)$row['articlePriceBrutto'];
                }
                $data['articles'][] = $row;
            }
        }

        return new JsonResponse($data);
    }

    #[Route('/book', name: 'book', methods: ['POST'])]
    #[IsGranted('ROLE_MEMBER')]
    public function book(Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
        $user = $this->security->getUser();
        $content = json_decode($request->getContent(), true);

        if (!$content || !isset($content['proposal_id'], $content['items'])) {
            return new JsonResponse(['error' => 'Invalid JSON or missing fields'], 400);
        }

        $proposalId = (int)$content['proposal_id'];
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
        // Optional: Notizen aus dem Buchungsformular übernehmen
        if (isset($content['notes']) && is_string($content['notes'])) {
            $booking->notes = $content['notes'];
        }

        if (!$booking->save()) {
            return new JsonResponse(['error' => 'Could not create booking'], 500);
        }

        // 2. Create Orders for each item (tl_dc_check_order)
        $totalPrice = 0;
        $itemResults = [];
        foreach ($content['items'] as $item) {
            $order = new DcCheckOrderModel();
            $order->tstamp = time();
            $order->pid = $booking->id;
            $order->bookingId = $booking->bookingNumber;
            $order->serialNumber = $item['serialNumber'] ?? '';
            $order->manufacturer = $item['manufacturer'] ?? '';
            $order->bazNumber = $item['bazNumber'] ?? '';
            $order->size = $item['size'] ?? '';
            $order->o2clean = (bool)($item['o2clean'] ?? false);
            $order->status = 'ordered';
            // Optional: Notizen pro Flasche/Position
            if (isset($item['notes']) && is_string($item['notes'])) {
                $order->notes = $item['notes'];
            }

            // Articles as blob/serialized array
            $articleIds = [];
            if (isset($item['articles']) && is_array($item['articles'])) {
                // Artikel-IDs auf Integer normalisieren
                $articleIds = array_values(array_filter(array_map(static function ($v) {
                    if (is_numeric($v)) {
                        return (int)$v;
                    }
                    return null;
                }, $item['articles']), static fn($v) => null !== $v));
                $order->selectedArticles = $articleIds; // Contao models handle serialization if it is a blob and multiple is true
            }

            // Calculate price using Helper
            $tankSize = (string)($item['size'] ?? '12');
            $itemPrice = (float)TankCheckHelper::calculateTotalPrice($proposalId, $tankSize, $articleIds);

            // Optional: Wenn der Preis explizit im POST mitgegeben wurde, diesen verwenden (falls abweichend)
            // oder als Fallback den berechneten Preis nehmen.
            if (isset($item['price']) && is_numeric($item['price'])) {
                $itemPrice = (float)$item['price'];
            }

            $order->totalPrice = (float)$itemPrice;
            $totalPrice += (float)$itemPrice;

            if (!$order->save()) {
                // Log error if needed
            }

            // Refetch or ensure totalPrice is in row for results
            $itemResults[] = [
                'serialNumber' => $order->serialNumber,
                'totalPrice' => (float)$itemPrice
            ];
        }

        // Update total price in booking
        $booking->totalPrice = (float)$totalPrice;
        $booking->save();

        return new JsonResponse([
            'success' => true,
            'booking_number' => $booking->bookingNumber,
            'total_price' => (float)$totalPrice,
            'items' => $itemResults
        ]);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = \Contao\System::getContainer()->get(ContaoFramework::class);
        }

        return $this->framework;
    }
}
