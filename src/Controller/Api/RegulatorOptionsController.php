<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Diversworld\ContaoDiveclubBundle\Helper\DcaTemplateHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/regulator/options', name: 'api_regulator_options_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class RegulatorOptionsController
{
    private ?DcaTemplateHelper $templateHelper = null;
    private ?ContaoFramework $framework = null;

    #[Route('', name: 'all', methods: ['GET'])]
    public function getOptions(): JsonResponse
    {
        $this->getFramework()->initialize();
        $templateHelper = $this->getTemplateHelper();

        $manufacturers = $templateHelper->getManufacturers();
        $regulators = $templateHelper->getTemplateOptions('regulatorsFile');

        return new JsonResponse([
            'manufacturers' => $manufacturers,
            'regulators' => $regulators,
        ]);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = System::getContainer()->get('contao.framework');
        }

        return $this->framework;
    }

    private function getTemplateHelper(): DcaTemplateHelper
    {
        if (null === $this->templateHelper) {
            $container = System::getContainer();
            $this->templateHelper = $container->has('diversworld.template.helper')
                ? $container->get('diversworld.template.helper')
                : $container->get(DcaTemplateHelper::class);
        }

        return $this->templateHelper;
    }
}
