<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcTanksModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/api/tanks', name: 'api_tanks_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class TankController extends AbstractController
{
    public function __construct(
        private Security $security,
        private ?ContaoFramework $framework = null
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->getFramework()->initialize();
        
        $user = $this->security->getUser();
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $models = DcTanksModel::findAll();
        } elseif ($user) {
            $models = DcTanksModel::findBy('owner', $user->id);
        } else {
            $models = DcTanksModel::findPublished();
        }

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $row = $model->row();

            // Convert date fields to timestamp
            foreach (['tstamp', 'lastCheckDate', 'nextCheckDate', 'start', 'stop', 'lastOrder'] as $field) {
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
        $model = DcTanksModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank not found'], 404);
        }

        // Security check: Only owner or admin can see details (except if it's public)
        $user = $this->security->getUser();
        if (!$this->security->isGranted('ROLE_ADMIN') && ($user === null || (int)$model->owner !== (int)$user->id)) {
            if (!$model->published) {
                return new JsonResponse(['error' => 'Access denied'], 403);
            }
        }

        $row = $model->row();

        // Convert date fields to timestamp
        foreach (['tstamp', 'lastCheckDate', 'nextCheckDate', 'start', 'stop', 'lastOrder'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = (int)$row[$field];
            }
        }

        return new JsonResponse($row);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Basic validation
        if (empty($data['title']) || empty($data['serialNumber']) || empty($data['size'])) {
            return new JsonResponse(['error' => 'Missing required fields: title, serialNumber, size'], 400);
        }

        // Auto-assign owner if logged in and not provided
        if (empty($data['owner']) && ($user = $this->security->getUser())) {
            $data['owner'] = $user->id;
        }

        $model = new DcTanksModel();
        $this->updateModelWithData($model, $data);
        $model->tstamp = time();
        $model->save();

        return new JsonResponse(['success' => true, 'id' => (int)$model->id], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->getFramework()->initialize();
        $model = DcTanksModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank not found'], 404);
        }

        // Security check: Only owner or admin can update
        $user = $this->security->getUser();
        if (!$this->security->isGranted('ROLE_ADMIN') && ($user === null || (int)$model->owner !== (int)$user->id)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $this->updateModelWithData($model, $data);
        $model->tstamp = time();
        $model->save();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $this->getFramework()->initialize();
        $model = DcTanksModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank not found'], 404);
        }

        // Security check: Only owner or admin can delete
        $user = $this->security->getUser();
        if (!$this->security->isGranted('ROLE_ADMIN') && ($user === null || (int)$model->owner !== (int)$user->id)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $model->delete();

        return new JsonResponse(['success' => true]);
    }

    private function updateModelWithData(DcTanksModel $model, array $data): void
    {
        $fields = [
            'title', 'alias', 'status', 'rentalFee', 'serialNumber', 
            'manufacturer', 'bazNumber', 'size', 'o2clean', 'owner', 
            'checkId', 'lastCheckDate', 'nextCheckDate', 'lastOrder',
            'addNotes', 'notes', 'published', 'start', 'stop'
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $model->$field = $data[$field];
            }
        }
    }

    private function getFramework(): ContaoFramework
    {
        if (null === $this->framework) {
            $this->framework = \Contao\System::getContainer()->get(ContaoFramework::class);
        }

        return $this->framework;
    }
}
