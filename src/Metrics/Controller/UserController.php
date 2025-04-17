<?php

namespace App\Metrics\Controller;

use App\Controller\ApiBaseController;
use App\Metrics\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends ApiBaseController
{
    #[Route('/users', name: 'users_activity', methods: ['GET'])]
    public function usersActivity(UserRepository $userRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $from = $requestQuery->get('from');
        $to = $requestQuery->get('to');

        $accountsCreatedPeriod = $userRepository->accountsCreatedPeriod($from, $to);
        $usersConnectedPeriod = $userRepository->usersConnectedPeriod($from, $to);
        $favoriteSportsPeriod = $userRepository->favoriteSportsPeriod($from, $to);
        $accountsCreatedTotal = $userRepository->accountsCreatedTotal()[0]["amount"];
        $favoriteSportsTotal = $userRepository->favoriteSportsTotal()[0]["amount"];
        $usersAcceptedLocationTotal = $userRepository->usersAcceptedLocationTotal()[0]["amount"];
        $usersAcceptedNotificationsTotal = $userRepository->usersAcceptedNotificationsTotal()[0]["amount"];
        $usersWithBioTotal = $userRepository->usersWithBioTotal()[0]["amount"];
        $userswithProfilePictureTotal = $userRepository->usersWithProfilePictureTotal()[0]["amount"];
        $activeUsersPeriod = $userRepository->activeUsersPeriod($from, $to);
        $messagesSent = $userRepository->messagesSent($from, $to);
        $favoriteSportsChosen = $userRepository->favoriteSportsChosen();
        $userLocations = $userRepository->userLocations();
        $platforms = $userRepository->platforms();

        return $this->json(
            [
                'accountsCreatedTotal' => $accountsCreatedTotal,
                'usersConnectedPeriod' => $usersConnectedPeriod,
                'favoriteSportsTotal' => $favoriteSportsTotal,
                'usersAcceptedLocationTotal' => $usersAcceptedLocationTotal,
                'usersAcceptedNotificationsTotal' => $usersAcceptedNotificationsTotal,
                'usersWithBioTotal' => $usersWithBioTotal,
                'usersWithProfilePictureTotal' => $userswithProfilePictureTotal,
                'platforms' => $platforms,
                'activeUsersPeriod' => $activeUsersPeriod,
                'accountsCreatedPeriod' => $accountsCreatedPeriod,
                'favoriteSportsPeriod' => $favoriteSportsPeriod,
                'messagesSent' => $messagesSent,
                'favoriteSportsChosen' => $favoriteSportsChosen,
                'userLocations' => $userLocations
            ],
            JsonResponse::HTTP_OK
        );
    }
}
