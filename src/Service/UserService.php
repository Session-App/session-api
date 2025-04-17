<?php

namespace App\Service;

use App\Repository\UserRepository;

class UserService
{
    private $userRepository;

    public function __construct(
        UserRepository $userRepository
    ) {

        $this->userRepository = $userRepository;
    }

    public function getAmountOfUsersInArea($requestQuery): int
    {
        $mapNorthEastLat = (float)$requestQuery->get('mapNorthEastLat');
        $mapNorthEastLng = (float)$requestQuery->get('mapNorthEastLng');
        $mapSouthWestLat = (float)$requestQuery->get('mapSouthWestLat');
        $mapSouthWestLng = (float)$requestQuery->get('mapSouthWestLng');

        // approximative distance in km
        $diagonalLength = sqrt((pow(69.1 * ($mapNorthEastLat - $mapSouthWestLat), 2) + pow(69.1 * ($mapNorthEastLng - $mapSouthWestLng), 2))) * 1.609;

        if ($diagonalLength > 200) {
            return 0;
        } else {
            return $this->userRepository->getUserAmountInArea($mapNorthEastLat, $mapNorthEastLng, $mapSouthWestLat, $mapSouthWestLng);
        }
    }
}
