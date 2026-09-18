<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Helper\DcaTemplateHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/equipment/options', name: 'api_equipment_options_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class EquipmentOptionsController
{
    public function __construct(
        private readonly ContaoFramework   $framework,
        private readonly DcaTemplateHelper $templateHelper,
    )
    {
    }

    #[Route('', name: 'all', methods: ['GET'])]
    public function getAllOptions(): JsonResponse
    {
        $this->framework->initialize();
        $templateHelper = $this->templateHelper;

        return new JsonResponse([
            'types' => $templateHelper->getEquipmentTypes(),
            'manufacturers' => $templateHelper->getManufacturers(),
            'sizes' => $templateHelper->getSizes(),
        ]);
    }

}
