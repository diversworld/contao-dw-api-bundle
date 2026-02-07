<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Diversworld\ContaoDiveclubBundle\Model\DcStudentsModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/students', name: 'api_students', defaults: ['_scope' => 'frontend', '_token_check' => false])]
#[IsGranted('ROLE_MEMBER')]
class StudentController extends AbstractController
{
    public function __construct(
        private readonly Security $security
    ) {
    }
    #[Route('', name: 'api_students_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $models = DcStudentsModel::findAll();

        if (null === $models) {
            return new JsonResponse([]);
        }

        $data = [];
        foreach ($models as $model) {
            $data[] = $model->row();
        }

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'api_students_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $model = DcStudentsModel::findByPk($id);

        if (null === $model) {
            return new JsonResponse(['error' => 'Student not found'], 404);
        }

        return new JsonResponse($model->row());
    }
}
