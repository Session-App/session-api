<?php

namespace App\Metrics\Controller;

use App\Controller\ApiBaseController;
use App\Metrics\Repository\SpotRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SpotController extends ApiBaseController
{
    #[Route('/spots', name: 'spots_activity', methods: ['GET'])]
    public function usersActivity(SpotRepository $spotRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;

        $spotsAddedPeriod = $spotRepository->spotsAddedPeriod($requestQuery->get('from'), $requestQuery->get('to'));

        return $this->json(
            ['spotsAddedPeriod' => $spotsAddedPeriod],
            JsonResponse::HTTP_OK
        );
    }
}
