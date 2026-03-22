<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Contao\FilesModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseEventScheduleModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseStudentsModel;
use Diversworld\ContaoDiveclubBundle\Model\DcCourseModulesModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/events', name: 'api_events_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class EventController extends AbstractController
{
    public function __construct(
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->getFramework()->initialize();
        $models = DcCourseEventModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();
            if (isset($row['singleSRC']) && $row['singleSRC']) {
                $row['singleSRC'] = StringUtil::binToUuid($row['singleSRC']);
            }

            // Convert date fields to timestamp
            foreach (['tstamp', 'startDate', 'endDate', 'dateStart', 'dateEnd', 'start', 'stop'] as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $row[$field] = (int)$row[$field];
                }
            }

            $data[] = $row;
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        $model = DcCourseEventModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        $data = $model->row();
        if (isset($data['singleSRC']) && $data['singleSRC']) {
            $data['singleSRC'] = StringUtil::binToUuid($data['singleSRC']);
        }

        // Convert date fields to timestamp
        foreach (['tstamp', 'startDate', 'endDate', 'dateStart', 'dateEnd', 'start', 'stop'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = (int)$data[$field];
            }
        }

        $data['imagePath'] = '';
        if ($model->addImage && $model->singleSRC) {
            $file = FilesModel::findByUuid($model->singleSRC);
            if ($file) {
                $data['imagePath'] = $file->path;
            }
        }

        $data['currentParticipants'] = (int)DcCourseStudentsModel::countBy(
            ['event_id=?', 'status=?'],
            [$model->id, 'registered']
        );

        return new JsonResponse($data);
    }

    #[Route('/{id}/schedule', name: 'schedule', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function schedule(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        $event = DcCourseEventModel::findByPk($id);

        if (null === $event) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        $schedule = DcCourseEventScheduleModel::findBy('pid', $id, ['order' => 'planned_at ASC']);

        if (null === $schedule) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($schedule as $item) {
            $row = $item->row();

            // Convert date fields to timestamp
            foreach (['tstamp', 'planned_at', 'start', 'stop'] as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $row[$field] = (int)$row[$field];
                }
            }

            // Add module title
            if ($item->module_id) {
                $module = DcCourseModulesModel::findByPk($item->module_id);
                if ($module) {
                    $row['module_title'] = html_entity_decode($module->title);
                }
            }

            $data[] = $row;
        }

        return new JsonResponse($data);
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = \Contao\System::getContainer()->get(ContaoFramework::class);
        }

        return $this->framework;
    }
}
