<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\ConversationRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\MessageRepository;
use App\Repository\SessionRepository;
use App\Service\ConversationService;
use App\Service\MessageService;
use Symfony\Component\HttpFoundation\JsonResponse;

class MessageController extends ApiBaseController
{
    #[Route('/messages/{conversationId}', name: 'get_messages', methods: ['GET'])]
    public function getMessages(MessageRepository $messageRepository, Request $request, int $conversationId): JsonResponse
    {
        $requestQuery = $request->query;
        $messages = $messageRepository->getMessagesFromConversation($conversationId, $this->getUser()->getId(), $requestQuery->get('page'), $requestQuery->get('size'));

        return $this->json(
            ['messages' => $messages],
            JsonResponse::HTTP_OK,
            ['groups' => ["collection:messages", "user:read:light", "session:id"]]
        );
    }

    #[Route('/messages', name: 'get_unseen_messages', methods: ['GET'])]
    public function getUnseenMessages(MessageRepository $messageRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $messages = $messageRepository->getUnseenMessages($this->getUser()->getId(), intval($requestQuery->get('lastMessageId')));

        return $this->json(
            ['messages' => $messages],
            JsonResponse::HTTP_OK,
            ['groups' => ["collection:messages", "user:read:light", "conversation:id", "session:id"]]
        );
    }

    #[Route('/messages', name: 'post_message', methods: ['POST'])]
    public function postMessage(
        Request $request,
        ConversationRepository $conversationRepository,
        MessageService $messageService
    ): JsonResponse {
        $user = $this->getUser();
        $result = false;

        $message = $this->serializer->deserialize(
            $request->getContent(),
            Message::class,
            'json'
        );
        $conversation = $conversationRepository->findOneBy(['id' => $message->getConversation()->getId()]);
        [$result, $message] = $messageService->sendMessage($message, $user, $conversation);

        return $this->json(
            ['message' => ['id' => $message->getId()]],
            $result ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN
        );
    }

    #[Route('/share', name: 'share_item', methods: ['POST'])]
    public function shareItem(
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationService $conversationService,
        SessionRepository $sessionRepository,
        MessageService $messageService
    ): JsonResponse {
        $user = $this->getUser();
        $result = false;
        $requestQuery = $request->query;

        $message = $this->serializer->deserialize(
            $request->getContent(),
            Message::class,
            'json'
        );
        //todo : traduction
        $message->setContent('Session partagée');
        $session = $sessionRepository->findOneBy(["id" => $requestQuery->get('sessionId')]);
        if ($requestQuery->get('userId')) {
            $conversation = $conversationRepository->doesConversationExist([$requestQuery->get('userId')], $user->getId(), null);
            if (!$conversation) {
                [$errors, $conversation] = $conversationService->createConversation([$requestQuery->get('userId')], $user, null, true);
            }
            [$result, $message] = $messageService->sendMessage($message, $user, $conversation, $session);
        } else if ($requestQuery->get('conversationId')) {
            [$result, $message] = $messageService->sendMessage($message, $user, $conversationRepository->findOneBy(["id" => $requestQuery->get('conversationId')]), $session);
        }

        return $this->json(
            ['message' => ['id' => $message->getId()]],
            $result ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN
        );
    }
}
