<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Helper\DcaTemplateHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sizes/options', name: 'api_sizes_options_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class SizesOptionsController
{
    public function __construct(
        private readonly ContaoFramework   $framework,
        private readonly DcaTemplateHelper $templateHelper,
    )
    {
    }

    #[Route('', name: 'all', methods: ['GET'])]
    public function getOptions(): JsonResponse
    {
        $this->framework->initialize();
        $templateHelper = $this->templateHelper;

        return new JsonResponse([
            'sizes' => $templateHelper->getSizes(),
            'manufacturers' => $templateHelper->getManufacturers(),
        ]);
    }

}
