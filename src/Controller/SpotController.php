<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\SpotRepository;
use App\Repository\SessionRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Spot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use Datetimeimmutable;
use App\Controller\ApiBaseController;
use App\Service\UserService;

class SpotController extends ApiBaseController
{
    #[Route("spots/favoriteSports", name: "get_spots_favorite_sports", methods: ["GET"])]
    public function getSpotsFavoriteSports(
        SpotRepository $spotRepository,
        Request $request,
    ): JsonResponse {
        $requestQuery = $request->query;

        $favoriteSports = [];

        foreach ($this->getUser()->getFavoriteSports() as $sport) {
            $favoriteSports[] = [$sport, $this->isGranted('ROLE_SECRET_' . $sport)];
        }

        $spots = $spotRepository->findSpotsInAreaFavoriteSports($requestQuery, $favoriteSports);

        return $this->json(
            [
                "spots" => $spots
            ],
            Response::HTTP_OK,
            ["groups" => ["collection:spots", "sport"]]
        );
    }

    #[Route("spots/{sportId}", name: "get_spots", methods: ["GET"])]
    public function getSpots(
        SpotRepository $spotRepository,
        Request $request,
        UserService $userService,
        int $sportId
    ): JsonResponse {
        $requestQuery = $request->query;
        $isVerified = $this->isGranted('ROLE_SECRET_' . $sportId);
        $spots = $spotRepository->findSpotsInArea($requestQuery, $sportId, $isVerified);
        $userAmountInArea = $userService->getAmountOfUsersInArea($requestQuery);

        return $this->json(
            [
                "spots" => $spots,
                "userAmountInArea" => $userAmountInArea
            ],
            Response::HTTP_OK,
            ["groups" => ["collection:spots"]]
        );
    }

    #[Route("spots/closests/{sportId}", name: "get_closest_spots", methods: ["GET"])]
    public function getClosestSpots(
        SpotRepository $spotRepository,
        Request $request,
        $sportId,
    ): JsonResponse {
        $requestQuery = $request->query;
        $isVerified = $this->isGranted('ROLE_SECRET_' . $sportId);

        if ($sportId == 'favoriteSports') {
            $sports = [];
            foreach ($this->getUser()->getFavoriteSports() as $sport) {
                $sports[] = [$sport, $this->isGranted('ROLE_SECRET_' . $sport)];
            }
        } else {
            $sports = [[$sportId, $this->isGranted('ROLE_SECRET_' . $sportId)]];
        }


        $spots = $spotRepository->findClosestSpots($requestQuery->get('lat'), $requestQuery->get('lon'), $sports, $requestQuery->get('page'), 20);
        foreach ($spots as $spot) {
            foreach ($spot->getPictures() as $pic) {
                if (!$pic->getValidated()) {
                    $spot->removePicture($pic);
                }
            }
        }

        return $this->json(
            $spots,
            Response::HTTP_OK,
            ["groups" => ["collection:spots", "item:spot"]]
        );
    }

    #[Route("spot/detail/{spotId}", methods: ["GET"])]
    public function item(SpotRepository $spotRepository, UserRepository $userRepository, $spotId): JsonResponse
    {
        $spot = $spotRepository->findOneBy(['id' => $spotId]);

        if ($spot !== null && $spot->getSecret() && !$this->isGranted('ROLE_SECRET_' . $spot->getSport())) {
            $spot = null;
        } else {
            foreach ($spot->getPictures() as $pic) {
                if (!$pic->getValidated()) {
                    $spot->removePicture($pic);
                }
            }
        }

        $isFavorite = $userRepository->isSpotFavorite($this->getUser()->getId(), $spotId);

        return $this->json(
            ['spot' => $spot, 'fav' => $isFavorite],
            $spot == null ? Response::HTTP_FORBIDDEN : Response::HTTP_OK,
            ["groups" => ["item:spot", "user:read:light"]]
        );
    }

    #[Route("spot/detail/brief/{spotId}", methods: ["GET"])]
    public function spotDetailBrief($spotId, SpotRepository $spotRepository, SerializerInterface $serializer): JsonResponse
    {
        $spot = $spotRepository->findOneBy(["id" => $spotId]);

        if ($spot !== null && $spot->getSecret() && !$this->isGranted('ROLE_SECRET_' . $spot->getSport())) {
            $spot = null;
        }


        return $this->json(
            $spot,
            $spot == null ? Response::HTTP_FORBIDDEN : Response::HTTP_OK,
            ["groups" => ["item:spot:brief"]]
        );
    }

    #[Route("spot/{spotId}/sessions", methods: ["GET"])]
    public function sessions(
        $spotId,
        SessionRepository $sessionRepository,
        SpotRepository $spotRepository,
        SerializerInterface $serializer,
        Request $request
    ): JsonResponse {
        $requestQuery = $request->query;

        $sessions = $sessionRepository->getSpotSessionsInPeriod($spotId, $requestQuery->get('begin'), $requestQuery->get('end'));
        return $this->json(
            $sessions,
            JsonResponse::HTTP_OK,
            ["groups" => ["item:spot"]]
        );
    }

    #[Route("spot/addTags", methods: ["POST"])]
    public function addTags(
        Request $request,
        EntityManagerInterface $entityManager,
        SpotRepository $spotRepository
    ): JsonResponse {
        $jsonRequest = json_decode($request->getContent());
        $spot = $spotRepository->findOneBy(['id' => $jsonRequest->spotId]);
        $user = $this->getUser();
        if ($spot->getTags() == []) {
            $spot->setTags($jsonRequest->tags);
            $user->increaseContribution(20);
            $entityManager->flush();
            $result = 'success';
        } else {
            $result = 'error.tags_already_set';
        }

        return $this->json(
            $result,
            $result == 'success' ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN,
        );
    }

    #[Route("spot/new/{sportId}", methods: ["POST"])]
    public function newSpot(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        SpotRepository $spotRepository,
        $sportId,
    ): JsonResponse {
        $spot = $serializer->deserialize($request->getContent(), Spot::class, 'json');
        $user = $this->getUser();
        $spot->setAddedBy($user);
        $spot->setSecret(false);
        $now = new DateTimeImmutable();
        $spot->setCreatedAt($now);
        $spot->setUpdatedAt($now);
        $spot->setSport($sportId);
        $user->increaseContribution(50);

        $possibleDuplicates = $spotRepository->findPossibleDuplicates($spot->getLat(), $spot->getLon(), $sportId);
        if ($possibleDuplicates != [] && $request->query->get('duplicateChecked') != "true") {
            return $this->json(
                ['errors' => ['possibleDuplicate' => $possibleDuplicates[0]]],
                JsonResponse::HTTP_CONFLICT,
                ['groups' => ['item:spot', 'collection:spots']]
            );
        } else {
            $entityManager->persist($spot);
            $entityManager->flush();

            return $this->json(
                ['id' => $spot->getId()],
                JsonResponse::HTTP_CREATED,
            );
        }
    }
}
