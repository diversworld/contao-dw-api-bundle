<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\NewsModel;
use Contao\StringUtil;
use Contao\System;
use Diversworld\ContaoDiveclubBundle\Model\DcConfigModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function newsList(Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
        $config = DcConfigModel::findOneBy('published', '1');

        if (!$config || !$config->activateApi) {
            return new JsonResponse([]);
        }

        $pids = [];
        $archiveParam = $request->query->get('archive');

        if ($archiveParam) {
            if (is_array($archiveParam)) {
                $pids = array_map('intval', $archiveParam);
            } else {
                // Handle [1] format if passed as string
                $pids = array_map('intval', explode(',', trim($archiveParam, '[] ')));
            }
        }

        if (empty($pids) && $config->apiNewsArchive) {
            $pids = [(int)$config->apiNewsArchive];
        }

        if (empty($pids)) {
            return new JsonResponse([]);
        }

        $limit = (int)$request->query->get('limit', 0);
        $time = time();
        $news = NewsModel::findBy(
            [
                "tl_news.pid IN(" . implode(',', array_map('intval', $pids)) . ")",
                "tl_news.published='1'",
                "(tl_news.start='' OR tl_news.start<=$time)",
                "(tl_news.stop='' OR tl_news.stop>$time)"
            ],
            null,
            ['limit' => $limit, 'order' => 'tl_news.date DESC']
        );

        if (!$news) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($news as $item) {
            $imagePath = '';

            // Check if addImage is set and singleSRC is provided
            if ($item->addImage && $item->singleSRC) {
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

    #[Route('/news/details', name: 'news_details_query', methods: ['GET'])]
    public function newsDetailsQuery(Request $request): JsonResponse
    {
        $id = (int)$request->query->get('id');
        return $this->newsDetail($id);
    }

    #[Route('/news/{id}', name: 'news_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function newsDetail(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        $time = time();
        $item = NewsModel::findBy(
            [
                'tl_news.id=?',
                'tl_news.published=1',
                "(tl_news.start='' OR tl_news.start<=$time)",
                "(tl_news.stop='' OR tl_news.stop>$time)"
            ],
            [$id]
        );

        if (!$item) {
            return new JsonResponse(['error' => 'News not found'], 404);
        }

        $imagePath = '';
        if ($item->addImage && $item->singleSRC) {
            $file = FilesModel::findByUuid($item->singleSRC);
            if ($file) {
                $imagePath = $file->path;
            }
        }

        // Get full content from content elements
        $content = '';
        $images = [];

        if ($imagePath) {
            $images[] = $imagePath;
        }

        $elements = ContentModel::findPublishedByPidAndTable($item->id, 'tl_news');
        if ($elements) {
            foreach ($elements as $element) {
                // Collect text content
                if ($element->type === 'text') {
                    $content .= StringUtil::restoreBasicEntities($element->text) . "\n\n";
                }

                // Collect images from various content elements
                if (in_array($element->type, ['image', 'text'], true) && $element->singleSRC) {
                    $file = FilesModel::findByUuid($element->singleSRC);
                    if ($file && !in_array($file->path, $images, true)) {
                        $images[] = $file->path;
                    }
                }
            }
        }

        // Use teaser as fallback for text if no content elements were found
        if (empty(trim($content))) {
            $content = StringUtil::restoreBasicEntities($item->teaser);
        }

        return new JsonResponse([
            'id' => (int)$item->id,
            'date' => (int)$item->date,
            'headline' => $item->headline,
            'teaser' => StringUtil::restoreBasicEntities($item->teaser),
            'text' => $content,
            'image' => $imagePath,
            'images' => $images,
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
