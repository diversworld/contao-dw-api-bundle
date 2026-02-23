<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Diversworld\ContaoDiveclubBundle\Helper\DcaTemplateHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tanks/options', name: 'api_tanks_options_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class TankOptionsController extends AbstractController
{
    public function __construct(
        private readonly DcaTemplateHelper $templateHelper,
        private ?ContaoFramework           $framework = null
    )
    {
    }

    #[Route('', name: 'all', methods: ['GET'])]
    public function getOptions(): JsonResponse
    {
        $this->getFramework()->initialize();

        return new JsonResponse([
            'sizes' => $this->templateHelper->getSizes(),
            'manufacturers' => $this->templateHelper->getManufacturers(),
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
