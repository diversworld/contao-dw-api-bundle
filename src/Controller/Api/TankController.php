<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcTanksModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tanks', name: 'api_tanks_', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class TankController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security        $security,
    )
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $user = $this->security->getUser();
        $status = $request->query->get('status');

        if ($status === 'available') {
            $models = DcTanksModel::findBy(['published=?', 'status=?'], [1, 'available']);
        } elseif ($status === 'owned') {
            if (!$user) {
                return new JsonResponse(['error' => 'Not authenticated'], 401);
            }
            $models = DcTanksModel::findBy(['owner=?', 'status=?'], [$user->id, 'owned']);
        } elseif ($this->security->isGranted('ROLE_ADMIN')) {
            $models = DcTanksModel::findAll();
        } elseif ($user) {
            // Für angemeldete Mitglieder: Eigene Tanks UND alle als "verfügbar" veröffentlichten Tanks laden
            $models = DcTanksModel::findBy(['(owner=? OR (published=? AND status=?))'], [$user->id, 1, 'available']);
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
        $this->framework->initialize();
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
    #[IsGranted('ROLE_MEMBER')]
    public function create(Request $request): JsonResponse
    {
        $this->framework->initialize();
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $data = $this->decodeJsonPayload($request);

        if (null === $data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Basic validation
        if (empty($data['title']) || empty($data['serialNumber']) || empty($data['size'])) {
            return new JsonResponse(['error' => 'Missing required fields: title, serialNumber, size'], 400);
        }

        // Never trust the owner field from mobile clients. Regular members can
        // create tanks only for themselves; admins may explicitly assign another owner.
        if (!$this->security->isGranted('ROLE_ADMIN') || empty($data['owner'])) {
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
        $this->framework->initialize();
        $model = DcTanksModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Tank not found'], 404);
        }

        // Security check: Only owner or admin can update
        $user = $this->security->getUser();
        if (!$this->security->isGranted('ROLE_ADMIN') && ($user === null || (int)$model->owner !== (int)$user->id)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $data = $this->decodeJsonPayload($request);
        if (null === $data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        if (!$this->security->isGranted('ROLE_ADMIN')) {
            unset($data['owner']);
        }

        $this->updateModelWithData($model, $data);
        $model->tstamp = time();
        $model->save();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $this->framework->initialize();
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

}
