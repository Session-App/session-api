<?php

namespace App\Service;

use App\Entity\Message;
use App\Repository\ConversationRepository;
use App\Repository\SessionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

class MessageService
{
    public function __construct(
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        ConversationRepository $conversationRepository,
        PushNotificationsService $notifService,
        HubInterface $hub,
        SessionRepository $sessionRepository
    ) {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->conversationRepository = $conversationRepository;
        $this->notifService = $notifService;
        $this->hub = $hub;
        $this->sessionRepository = $sessionRepository;
    }

    public function sendMessage($message, $user, $conversation, $session = null): array
    {
        $message->setConversation($conversation);
        $result = false;

        if ($this->conversationRepository->isUserInConversation($user->getId(), $message->getConversation()->getId()) && $message->getContent()) {
            $message->setSentBy($user);
            $message->setSentAt(new Datetime());
            $message->getConversation()->setLastMessage($message);
            if ($session) {
                $message->setSession($session);
            }
            //$em->merge($message);
            $this->em->flush();
            $result = true;

            // mercure
            $conversation = $message->getConversation();
            $topics = [];
            $usersInConversation = [];
            $topicBase = 'conversations/';
            foreach ($conversation->getMembers() as $member) {
                $memberId = $member->getId();
                if ($memberId != $user->getId()) {
                    $topics[] = $topicBase . $memberId;
                    $usersInConversation[] = $member;
                }
            }
            if ($conversation->getName() == null && $conversation->getRecipient()->getId() != $user->getId()) {
                $topics[] = $topicBase . $conversation->getRecipient()->getId();
                $usersInConversation[] = $conversation->getRecipient();
            }
            if ($conversation->getAdministrator()->getId() != $user->getId()) {
                $topics[] = $topicBase . $conversation->getAdministrator()->getId();
                $usersInConversation[] = $conversation->getAdministrator();
            }
            $update = new Update(
                $topics,
                json_encode([
                    'message' => [
                        'id' => $message->getId(),
                        'content' => $message->getContent(),
                        'sentBy' => [
                            'id' => $user->getId(),
                            'username' => $user->getUsername(),
                            'profilePicture' => $user->getProfilePicture()
                        ]
                    ],
                    'conversation' => $conversation->getId()
                ]),
                true
            );
            try {
                $this->hub->publish($update);
            } catch (Exception $e) {
            }

            $usersToReceiveNotifications = [];
            $conversationId = $conversation->getId();
            foreach ($usersInConversation as $u) {
                if (!in_array($conversationId, $u->getDisabledConversationNotifications())) $usersToReceiveNotifications[] = $u;
            }

            $notifData = ['conversation' => $conversation->getid(), 'goTo' => 'conversation'];
            $this->notifService->sendNotifications($usersToReceiveNotifications, '', 'new_message', ['username' => $user->getUsername(), 'message' => $message->getContent()], $notifData);
        }
        return [$result, $message];
    }
}
