<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\NewsModel;
use Contao\StringUtil;
use Contao\System;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/app', name: 'api_app_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class AppController extends AbstractController
{
    public function __construct(
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('/config', name: 'config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        $this->getFramework()->initialize();
        $config = DcConfigModel::findOneBy('published', '1');

        if (!$config) {
            return new JsonResponse(['error' => 'No active configuration found'], 404);
        }

        $logoPath = '';
        if ($config->apiLogo) {
            $file = FilesModel::findByUuid($config->apiLogo);
            if ($file) {
                $logoPath = $file->path;
            }
        }

        return new JsonResponse([
            'activateApi' => (bool)$config->activateApi,
            'logo' => $logoPath,
            'infoText' => StringUtil::restoreBasicEntities($config->apiText),
            'newsArchive' => (int)$config->apiNewsArchive,
            'imprint' => StringUtil::restoreBasicEntities($config->apiImprint),
            'privacy' => StringUtil::restoreBasicEntities($config->apiPrivacy),
            'terms' => StringUtil::restoreBasicEntities($config->apiTerms),
        ]);
    }

    #[Route('/news', name: 'news_list', methods: ['GET'])]
    public function newsList(): JsonResponse
    {
        $this->getFramework()->initialize();
        $config = DcConfigModel::findOneBy('published', '1');

        if (!$config || !$config->activateApi || !$config->apiNewsArchive) {
            return new JsonResponse([]);
        }

        $news = NewsModel::findPublishedByPids([$config->apiNewsArchive]);

        if (!$news) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($news as $item) {
            $row = $item->row();

            $imagePath = '';
            if ($item->singleSRC) {
                $file = FilesModel::findByUuid($item->singleSRC);
                if ($file) {
                    $imagePath = $file->path;
                }
            }

            $data[] = [
                'id' => (int)$item->id,
                'date' => (int)$item->date,
                'headline' => $item->headline,
                'teaser' => StringUtil::restoreBasicEntities($item->teaser),
                'image' => $imagePath,
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/news/{id}', name: 'news_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function newsDetail(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        $item = NewsModel::findByPk($id);

        if (!$item || !$item->published) {
            return new JsonResponse(['error' => 'News not found'], 404);
        }

        $imagePath = '';
        if ($item->singleSRC) {
            $file = FilesModel::findByUuid($item->singleSRC);
            if ($file) {
                $imagePath = $file->path;
            }
        }

        // We use text to provide full content. 
        // Contao stores content elements for news, but also has a 'teaser' field.
        // If news has content elements, we might need to render them or just return the text field if used.
        // Usually, news content is rendered via content elements. 
        // For a simple API, returning the 'teaser' and maybe a main content if available.

        return new JsonResponse([
            'id' => (int)$item->id,
            'date' => (int)$item->date,
            'headline' => $item->headline,
            'teaser' => StringUtil::restoreBasicEntities($item->teaser),
            'text' => StringUtil::restoreBasicEntities($item->teaser), // Fallback if no content elements handled
            'image' => $imagePath,
        ]);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = System::getContainer()->get(ContaoFramework::class);
        }

        return $this->framework;
    }
}
