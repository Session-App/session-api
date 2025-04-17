<?php

namespace App\Controller;

use App\Entity\Session;
use App\Repository\SessionRepository;
use App\Controller\AbstractController;
use App\Entity\Message;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Entity\Spot;
use App\Repository\ConversationRepository;
use Symfony\Component\Serializer\SerializerInterface;
use App\Service\PushNotificationsService;
use Datetimeimmutable;
use App\Repository\SpotRepository;
use App\Repository\UserRepository;
use App\Service\MessageService;

use function json_decode;
use function json_encode;

class SessionController extends ApiBaseController
{
    #[Route('/sessions/favoriteSports', methods: ["GET"])]
    public function getSessionsFavoriteSports(SpotRepository $spotRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;

        $favoriteSports = [];

        foreach ($this->getUser()->getFavoriteSports() as $sport) {
            $favoriteSports[] = [$sport, $this->isGranted('ROLE_SECRET_' . $sport)];
        }
        $spots = $spotRepository->findSpotsWithSessions($favoriteSports, $requestQuery->get('begin'), $requestQuery->get('end'));

        return $this->json(
            [
                "spots" => $spots
            ],
            Response::HTTP_OK,
            ["groups" => ["collection:sessions", "sport"]]
        );
    }

    #[Route('/sessions/allSports', methods: ["GET"])]
    public function getSessionsAllSports(SpotRepository $spotRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $spots = $spotRepository->findSpotsWithSessionsAllSports($requestQuery->get('begin'));
        return $this->json(
            [
                "spots" => $spots
            ],
            Response::HTTP_OK,
            ["groups" => ["collection:sessions", "sport"]]
        );
    }

    #[Route('/sessions/{sportId}', methods: ["GET"])]
    public function getSessions(SpotRepository $spotRepository, Request $request, int $sportId): JsonResponse
    {
        $requestQuery = $request->query;
        $isVerified = $this->isGranted('ROLE_SECRET_' . $sportId);
        $spots = $spotRepository->findSpotsWithSessions([[$sportId, $isVerified]], $requestQuery->get('begin'), $requestQuery->get('end'));
        return $this->json(
            [
                "spots" => $spots
            ],
            Response::HTTP_OK,
            ["groups" => ["collection:sessions"]]
        );
    }

    #[Route("session/detail/{sessionId}", methods: ["GET"])]
    public function item(Request $request, $sessionId, SessionRepository $sessionsRepository): JsonResponse
    {
        $requestQuery = $request->query;
        $session = $sessionsRepository->findOneBy(['id' => $sessionId]);
        if ($session->getSpot()->getSecret() && !$this->isGranted('ROLE_SECRET_' . $session->getSpot()->getSport())) {
            $session = null;
        }

        $serilizationGroups = ["user:read:light", "collection:sessions"];
        if ($requestQuery->get('brief') == 'false') {
            $serilizationGroups[] = "item:session";
        }
        return $this->json(
            $session,
            $session == null ? Response::HTTP_FORBIDDEN : Response::HTTP_OK,
            ["groups" => $serilizationGroups]
        );
    }

    #[Route("session/join/{sessionId}", methods: ["POST"])]
    public function joinSession(
        EntityManagerInterface $entityManager,
        SessionRepository $sessionsRepository,
        PushNotificationsService $notifService,
        ConversationRepository $conversationRepository,
        $sessionId
    ): JsonResponse {
        $session = $sessionsRepository->findOneBy(['id' => $sessionId]);
        $errors = [];
        if ($session->getPrivate() != null && $conversationRepository->isUserInConversation($this->getUser()->getId(), $session->getPrivate()->getId()) == null) {
            $errors[] = "you_are_not_conversation_member";
        }
        if ($session->isFull()) {
            $errors[] = "session_is_full";
        }
        if ($errors === []) {
            $user = $this->getUser();
            $session->addParticipant($user);
            $entityManager->flush();
            $participantsAndOrganiser = $session->getParticipants();
            $participantsAndOrganiser[] = $session->getCreatedBy();
            $notifData = ['sessionId' => $sessionId, 'goTo' => 'sessionDetail'];
            $notifService->sendNotifications($participantsAndOrganiser, '', 'new_session_participant', ['username' => $user->getUsername()], $notifData);
        }

        return $this->json(
            [
                'errors' => $errors,
            ],
            $errors === [] ? Response::HTTP_OK : Response::HTTP_FORBIDDEN,
        );
    }

    #[Route("session/quit/{sessionId}", methods: ["POST"])]
    public function quitSession(
        EntityManagerInterface $entityManager,
        SessionRepository $sessionRepository,
        $sessionId
    ): JsonResponse {
        $session = $sessionRepository->findOneBy(['id' => $sessionId]);
        $session->removeParticipant($this->getUser());
        $entityManager->flush();

        return $this->json(
            [
                'result' => 'success',
            ],
            JsonResponse::HTTP_OK,
        );
    }
    #[Route('/session/new', methods: ["POST"])]
    public function post(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        SpotRepository $spotRepository,
        UserRepository $userRepository,
        ConversationRepository $conversationRepository,
        MessageService $messageService,
        PushNotificationsService $notifService
    ): JsonResponse {
        $jsonRequest = json_decode($request->getContent());
        $session = $serializer->deserialize($request->getContent(), Session::class, 'json');
        $user = $this->getUser();
        $spotId = $jsonRequest->spot->id;
        $spot = $spotRepository->findOneBy(['id' => $spotId]);
        $conversation = null;
        $errors = [];

        $session->setSpot($spot);
        $session->setCreatedAt(new DateTimeImmutable());
        if ($session->getPrivate() != null) {
            $conversation = $conversationRepository->findOneBy(['id' => $jsonRequest->private->id]);
            if ($conversationRepository->isUserInConversation($user->getid(), $conversation->getId())) {
                $session->setPrivate($conversation);
            } else {
                $session->setPrivate(null);
                $errors[] = "you_are_not_conversation_member";
            }
        }
        $session->setCreatedBy($user);
        $user->increaseContribution(25);
        $entityManager->persist($session);
        $entityManager->flush();

        if ($session->getPrivate() == null) {
            $nearbyUsers = $userRepository->findNearbyUsers($spot->getLat(), $spot->getLon(), $user->getId(), $spot->getSport());
            $notifData = ['sessionId' => $session->getid(), 'goTo' => 'sessionDetail'];
            $notifService->sendNotifications($nearbyUsers, '', 'session_nearby', [], $notifData);
        } else {
            $message = new Message;
            //todo : translate
            $message->setContent('Session privée');
            [$result, $message] = $messageService->sendMessage($message, $user, $conversation, $session);
        }

        return $this->json(
            [
                'id' => $session->getId(),
                'erros' => $errors
            ],
            Response::HTTP_CREATED
        );
    }
}
