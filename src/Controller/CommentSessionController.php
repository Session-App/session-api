<?php

namespace App\Controller;

use App\Controller\ApiBaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Entity\Session;
use App\Entity\CommentSession;
use App\Service\PushNotificationsService;
use Symfony\Component\Serializer\SerializerInterface;
use Datetimeimmutable;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use function json_decode;
use function json_encode;

class CommentSessionController extends ApiBaseController
{
    #[Route('/session/comment/new', methods: ["POST"])]
    public function post(
        Request $request,
        EntityManagerInterface $entityManager,
        SessionRepository $sessionRepository,
        SerializerInterface $serializer,
        UrlGeneratorInterface $urlGenerator,
        UserRepository $userRepository,
        PushNotificationsService $notifService
    ): JsonResponse {
        $comment = $serializer->deserialize($request->getContent(), CommentSession::class, 'json');
        $comment->setCreatedAt(new DateTimeImmutable());
        $sessionId = json_decode($request->getContent())->session->id;
        $session = $sessionRepository->findOneBy(['id' => $sessionId]);
        $comment->setSession($session);
        $user = $this->getUser();
        $comment->setAddedBy($user);

        // $participantsAndOrganiser = $session->getParticipants();
        // $participantsAndOrganiser[] = $session->getCreatedBy();
        $usersInvolved = $userRepository->getUsersInvolvedInSession($sessionId);
        $notifData = ['sessionId' => $sessionId, 'goTo' => 'sessionDetail'];
        $notifService->sendNotifications($usersInvolved, '', 'comment_session', ['username' => $user->getUsername()], $notifData);

        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->json(
            [
                'comment' => $comment
            ],
            JsonResponse::HTTP_OK,
            ['groups' => ['item:session', 'user:read:light']]
        );
    }
}
