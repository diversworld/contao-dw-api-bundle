<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcEquipmentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/equipment', name: 'api_equipment_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class EquipmentController extends AbstractController
{
    public function __construct(
        private readonly \Diversworld\ContaoDiveclubBundle\Helper\DcaTemplateHelper $templateHelper,
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->getFramework()->initialize();
        $models = DcEquipmentModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        // Cache for labels
        $types = $this->templateHelper->getEquipmentFlatTypes();
        $manufacturers = $this->templateHelper->getManufacturers();
        $sizes = $this->templateHelper->getSizes();

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();

            // Convert date fields to timestamp
            foreach (['tstamp', 'buyDate', 'start', 'stop'] as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $row[$field] = (int)$row[$field];
                }
            }

            // Explicitly ensure numeric fields are numeric
            if (isset($row['rentalFee'])) {
                $row['rentalFee'] = (float)$row['rentalFee'];
            }

            // Ensure type and subType are included (as requested)
            // We map them to the names used in ReservationController if they differ
            $row['types'] = $row['type'] ?? '';
            $row['sub_type'] = $row['subType'] ?? '';

            // Add Labels
            $row['type_label'] = $types[$row['type']] ?? '-';
            $subTypes = $this->templateHelper->getSubTypes((int)$row['type']);
            $row['sub_type_label'] = $subTypes[$row['subType']] ?? '-';
            $row['manufacturer_label'] = $manufacturers[$row['manufacturer']] ?? '-';
            $row['size_label'] = $sizes[$row['size']] ?? '-';
            $row['status_label'] = $GLOBALS['TL_LANG']['tl_dc_equipment']['itemStatus'][$row['status']] ?? '-';

            $data[] = $row;
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        $model = DcEquipmentModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Equipment not found'], 404);
        }

        $row = $model->row();

        // Convert date fields to timestamp
        foreach (['tstamp', 'buyDate', 'start', 'stop'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        // Explicitly ensure numeric fields are numeric
        if (isset($row['rentalFee'])) {
            $row['rentalFee'] = (float)$row['rentalFee'];
        }

        // Ensure type and subType are included (as requested)
        $row['types'] = $row['type'] ?? '';
        $row['sub_type'] = $row['subType'] ?? '';

        // Add Labels
        $row['type_label'] = $this->templateHelper->getEquipmentFlatTypes()[$row['type']] ?? '-';
        $subTypes = $this->templateHelper->getSubTypes((int)$row['type']);
        $row['sub_type_label'] = $subTypes[$row['subType']] ?? '-';
        $row['manufacturer_label'] = $this->templateHelper->getManufacturers()[$row['manufacturer']] ?? '-';
        $row['size_label'] = $this->templateHelper->getSizes()[$row['size']] ?? '-';
        $row['status_label'] = $GLOBALS['TL_LANG']['tl_dc_equipment']['itemStatus'][$row['status']] ?? '-';

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
