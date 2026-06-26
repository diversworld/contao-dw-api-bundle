<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\System;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class MembersController
{
    private ?ContaoFramework $framework = null;

    #[Route('/members', name: 'members_list', methods: ['GET'])]
    public function members(): JsonResponse
    {
        $this->getFramework()->initialize();

        // Optional: API-Aktivierung über die Diveclub-Konfiguration prüfen (analog zu anderen Endpoints)
        $config = DcConfigModel::findOneBy('published', '1');
        if (!$config || !$config->activateApi) {
            return new JsonResponse([]);
        }

        $collection = MemberModel::findAll();
        if (!$collection) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($collection as $member) {
            $data[] = [
                'member_id' => (int)$member->id,
                'name' => (string)$member->lastname,
                'vorname' => (string)$member->firstname,
                'email' => (string)$member->email,
            ];
        }

        return new JsonResponse($data);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = System::getContainer()->get('contao.framework');
        }

        return $this->framework;
    }
}
