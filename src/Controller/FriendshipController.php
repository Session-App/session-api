<?php

namespace App\Controller;

use App\Entity\Friendship;
use App\Repository\FriendshipRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PushNotificationsService;
use App\Controller\ApiBaseController;
use Symfony\Component\HttpFoundation\Request;

class FriendshipController extends ApiBaseController
{
    #[Route("/friendship/request/{requestedUserId}", name:"request_friendship", methods:["GET"])]
        public function requestFriendship(
            FriendshipRepository $friendshipRepository,
            EntityManagerInterface $em,
            UserRepository $userRepository,
            PushNotificationsService $notifService,
            int $requestedUserId
        ): JsonResponse {
            $requester = $this->getUser();
            $requested = $userRepository->findOneBy(['id' => $requestedUserId]);
            if ($friendshipRepository->doesFriendshipExist($requestedUserId, $requester->getId()) != null) {
                $result = 'friendship_already_exists';
            } elseif ($requested == null) {
                $result = 'unknown_user';
            } else {
                $friendship = new Friendship();
                $friendship->setRequester($requester);
                $friendship->setRequested($requested);

                $notifData = ['userId' => $requester->getId(), 'goTo' => 'user'];
                $notifService->sendNotifications([$requested], '', 'friendship_request_received', ['username' => $requester->getUsername()], $notifData);

                $em->persist($friendship);
                $em->flush();
                $result = 'success';
            }

            return $this->json(
                $result,
                $result == 'success' ? Response::HTTP_OK : Response::HTTP_FORBIDDEN
            );
        }

    #[Route("/friendship/{action}/{requesterUserId}", name:"edit_friendship", methods:["GET"])]
        public function editFriendship(
            FriendshipRepository $friendshipRepository,
            EntityManagerInterface $em,
            UserRepository $userRepository,
            PushNotificationsService $notifService,
            int $requesterUserId,
            string $action
        ): JsonResponse {
            $requested = $this->getUser();
            $requester = $userRepository->findOneBy(['id' => $requesterUserId]);
            $friendship = $friendshipRepository->doesFriendshipExist($requesterUserId, $requested->getId());
            if ($friendship != null && $action == 'accept') {
                $friendship->setAcceptedAt(new DateTime());

                $notifData = ['userId' => $requested->getId(), 'goTo' => 'user'];
                $notifService->sendNotifications([$requester], '', 'friendship_request_accepted', ['username' => $requested->getUsername()], $notifData);

                $em->flush();
                $result = 'success';
            } elseif ($friendship != null && $action == 'reject') {
                $em->remove($friendship);
                $em->flush();
                $result = 'success';
            } elseif ($requester == null) {
                $result = 'unknown_user';
            } else {
                $result = 'friendship_inexistent';
            }

            return $this->json(
                $result,
                $result == 'success' ? Response::HTTP_OK : Response::HTTP_FORBIDDEN
            );
        }

    #[Route("/friendship/list", name:"list_friendships", methods:["GET"])]
        public function listFriendships(
            FriendshipRepository $friendshipRepository,
            Request $request
        ): JsonResponse {
            $friendships = $friendshipRepository->getFriendships($this->getUser()->getId(), $request->query->get('onlyFriends'));

            return $this->json(
                $friendships,
                Response::HTTP_OK,
                ["groups"=>["friendship:read", "user:read:light"]]
            );
        }
}
