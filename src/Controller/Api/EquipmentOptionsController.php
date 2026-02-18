<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Helper\DcaTemplateHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/equipment/options', name: 'api_equipment_options_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class EquipmentOptionsController extends AbstractController
{
    public function __construct(
        private readonly DcaTemplateHelper $templateHelper,
        private ?ContaoFramework           $framework = null
    )
    {
    }

    #[Route('', name: 'all', methods: ['GET'])]
    public function getAllOptions(): JsonResponse
    {
        $this->getFramework()->initialize();

        return new JsonResponse([
            'types' => $this->templateHelper->getEquipmentTypes(),
            'manufacturers' => $this->templateHelper->getManufacturers(),
            'sizes' => $this->templateHelper->getSizes(),
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
