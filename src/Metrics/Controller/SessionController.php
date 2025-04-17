<?php

namespace App\Metrics\Controller;

use App\Controller\ApiBaseController;
use App\Metrics\Repository\SessionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SessionController extends ApiBaseController
{
    #[Route('/sessions', name: 'sessions_activity', methods: ['GET'])]
    public function usersActivity(SessionRepository $sessionRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $from = $requestQuery->get('from');
        $to = $requestQuery->get('to');
        $sessionsCreatedTotal = $sessionRepository->sessionsCreatedTotal()[0]["amount"];
        $participantsTotal = $sessionRepository->participantsTotal()[0]["amount"];
        $sessionsCreatedPeriod = $sessionRepository->sessionsCreatedPeriod($from, $to);
        $participantsPeriod = $sessionRepository->participantsPeriod($to, $from);

        return $this->json(
            [
                'sessionsCreatedTotal' => $sessionsCreatedTotal,
                'participantsTotal' => $participantsTotal,
                'participantsPeriod' => $participantsPeriod,
                'sessionsCreatedPeriod' => $sessionsCreatedPeriod
            ],
            JsonResponse::HTTP_OK
        );
    }
}
