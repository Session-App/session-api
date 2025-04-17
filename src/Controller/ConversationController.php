<?php

namespace App\Controller;

use App\Entity\Conversation;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use App\Repository\SessionRepository;
use App\Service\ConversationService;
use App\Service\MercureTokenGenerator;
use App\Service\PushNotificationsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ConversationController extends ApiBaseController
{
    #[Route('/conversations', name: 'get_conversations', methods: ['GET'])]
    public function getConversations(ConversationRepository $conversationRepository, Request $request, MercureTokenGenerator $tokenGenerator): JsonResponse
    {
        $requestQuery = $request->query;
        $user = $this->getUser();
        $suggestedConversations = [];
        $conversations = $conversationRepository->getConversations($user->getId(), $requestQuery->get('page'), 100);
        if (count($conversations) <= 4) {
            $suggestedConversations = $conversationRepository->getSuggestedConversaions($user, 1, 10);
        }


        $mercureToken = $tokenGenerator->generateToken(['conversations/' . $user->getId()]);

        return $this->json(
            ['conversations' => $conversations, 'suggestedConversations' => $suggestedConversations, 'mercureToken' => $mercureToken],
            JsonResponse::HTTP_OK,
            ['groups' => ['collection:conversations', 'collection:messages', 'user:read:light']]
        );
    }

    #[Route('/conversation', name: 'get_conversation', methods: ['GET'])]
    public function getConversation(ConversationRepository $conversationRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $user = $this->getUser();
        $conversation = $conversationRepository->getConversation($user->getId(), $requestQuery->get('conversationId'));

        $serilizationConversations = ['collection:conversations', 'user:read:light'];
        if ($requestQuery->get('brief') == 'false') {
            $serilizationConversations[] = 'item:conversation';
        }

        return $this->json(
            ['conversation' => $conversation],
            JsonResponse::HTTP_OK,
            ['groups' => $serilizationConversations]
        );
    }

    #[Route('/conversation/members', name: 'get_conversation_members', methods: ['GET'])]
    public function getConversationMembers(UserRepository $userRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $user = $this->getUser();
        $page = $requestQuery->get('page');

        $members = $userRepository->getConversationMembers($user->getId(), $requestQuery->get('conversationId'), $page, $requestQuery->get('size'));

        $result = ['members' => $members];
        if ($page === '1') {
            $awaitingMembers = $userRepository->getConversationAwaitingMembers($user->getId(), $requestQuery->get('conversationId'));
            $result['awaitingMembers'] = $awaitingMembers;
            $result['total'] = $members->getTotalItemCount();
        }

        return $this->json(
            $result,
            JsonResponse::HTTP_OK,
            ['groups' => ['user:read:light']]
        );
    }

    #[Route('/conversations/find', name: 'find_conversation', methods: ['GET'])]
    public function findConversation(ConversationRepository $conversationRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $user = $this->getUser();
        $conversations = $conversationRepository->findConversations($user->getId(), $requestQuery->get('name'), $requestQuery->get('onlyIfMember'), 1, 100);

        return $this->json(
            ['conversations' => $conversations],
            JsonResponse::HTTP_OK,
            ['groups' => ['collection:conversations:foreign']]
        );
    }

    #[Route('/conversation/detail', name: 'conversation_detail_foreign', methods: ['GET'])]
    public function conversationDetail(ConversationRepository $conversationRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $user = $this->getUser();
        $conversation = $conversationRepository->getConversationDetail($user->getId(), $requestQuery->get('conversationId'));

        return $this->json(
            $conversation,
            JsonResponse::HTTP_OK,
            ['groups' => ['item:conversation:foreign']]
        );
    }

    #[Route('/conversation/join', name: 'join_conversation', methods: ['GET'])]
    public function join(ConversationRepository $conversationRepository, EntityManagerInterface $em, Request $request, PushNotificationsService $notifService,): JsonResponse
    {
        $requestQuery = $request->query;
        $conversation = $conversationRepository->findOneBy(["id" => $requestQuery->get('conversationId')]);
        $user = $this->getUser();
        $errors = [];

        if ($conversation) {
            if ($conversation->getName()) {
                if ($conversation->isPrivate()) {
                    $conversation->addAwaitingMember($user);
                    $notifData = ['conversation' => $conversation->getid(), 'goTo' => 'conversation'];
                    $notifService->sendNotifications($conversation->getAdministrator(), '', 'user_asked_join_conversation', ['username' => $user->getUsername(), 'group_name' => $conversation->getName()], $notifData);
                } else {
                    $conversation->addMember($this->getUser());
                }
                $em->flush();
            } else {
                $errors[] = 'not_a_group';
            }
        } else {
            $errors[] = 'conversation_not_found';
        }

        return $this->json(
            ['result' => $errors === [], 'errors' => $errors],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN,
        );
    }

    #[Route('/conversations/{conversationId}/addUser', name: 'validate_user_in_conversation', methods: ['POST'])]
    public function validateUser(ConversationService $conversationService, ConversationRepository $conversationRepository, UserRepository $userRepository, EntityManagerInterface $em, Request $request, int $conversationId): JsonResponse
    {
        $conversation = $conversationRepository->getConversation($this->getUser()->getId(), $conversationId);
        $jsonRequest = json_decode($request->getContent());
        $errors = $conversationService->isAdmin($this->getUser(), $conversation);

        if ($errors === []) {
            foreach ($jsonRequest->users as $user) {
                $newMember = $userRepository->findOneBy(['id' => $user]);
                if ($newMember) {
                    $conversation->addMember($newMember);
                    $conversation->removeAwaitingMember($newMember);
                } else {
                    $errors[] = 'user_not_found';
                }
            }
            if ($errors === []) {
                $em->flush();
            }
        }

        return $this->json(
            ['result' => $errors === [], 'errors' => $errors],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN,
        );
    }

    #[Route('/conversations/{conversationId}/removeUser', name: 'remove_user_in_conversation', methods: ['POST'])]
    public function removeUser(ConversationService $conversationService, ConversationRepository $conversationRepository, UserRepository $userRepository, EntityManagerInterface $em, Request $request, int $conversationId): JsonResponse
    {
        $conversation = $conversationRepository->getConversation($this->getUser()->getId(), $conversationId);
        $jsonRequest = json_decode($request->getContent());
        $errors = $conversationService->isAdmin($this->getUser(), $conversation);

        if ($errors === []) {
            $newMember = $userRepository->findOneBy(['id' => $jsonRequest->userId]);
            if ($newMember) {
                $conversation->removeMember($newMember);
                $conversation->removeAwaitingMember($newMember);
            } else {
                $errors[] = 'user_not_found';
            }
            if ($errors === []) {
                $em->flush();
            }
        }

        return $this->json(
            ['result' => $errors === [], 'errors' => $errors],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN,
        );
    }

    #[Route('/conversations/{conversationId}/quit', name: 'quit_conversation', methods: ['GET'])]
    public function quitConversation(ConversationRepository $conversationRepository, EntityManagerInterface $em, int $conversationId): JsonResponse
    {
        $conversation = $conversationRepository->getConversation($this->getUser()->getId(), $conversationId);
        $errors = [];

        if (!$conversation) {
            $errors[] = 'conversation_not_found';
        }
        if ($errors === []) {
            $conversation->removeMember($this->getUser());
            $em->flush();
        }

        return $this->json(
            ['result' => $errors === [], 'errors' => $errors],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN,
        );
    }

    #[Route('/conversations', name: 'create_conversation', methods: ['POST'])]
    public function createConversation(
        Request $request,
        ConversationRepository $conversationRepository,
        ConversationService $conversationService
    ): JsonResponse {
        $jsonRequest = json_decode($request->getContent());
        $creator = $this->getUser();
        $members = $jsonRequest->members;
        $errors = [];

        $conversation = null;

        if (count($members) > 1 && $jsonRequest->name == null) {
            // group
            $errors[] = 'choose_a_name';
        } else if (count($members) <= 1) {
            //oneToOne
            $jsonRequest->lat = null;
            $jsonRequest->lon = null;
            $jsonRequest->locationName = null;
            $jsonRequest->sport = null;
        }

        // creating multiple conversations with the same ppl is allowed only if the name is not null (so a real group, not 1 to 1)
        if ($jsonRequest->name == null && $errors == []) {
            $conversation = $conversationRepository->doesConversationExist($members, $creator->getId(), null);
        }

        $conversationAlreadyExists = false;
        if (!$conversation && $errors == []) {
            [$errors, $conversation] = $conversationService->createConversation($members, $creator, $jsonRequest->name, $jsonRequest->private, $jsonRequest->sport, $jsonRequest->lat, $jsonRequest->lon, $jsonRequest->locationName);
        } else {
            $conversationAlreadyExists = true;
        }

        return $this->json(
            ['conversation' => $conversation, 'conversationAlreadyExisted' => $conversationAlreadyExists, 'errors' => $errors],
            $errors == [] ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_NOT_FOUND,
            ['groups' => ['collection:conversations', 'user:read:light']]
        );
    }

    #[Route('/share', name: 'get_sharable_people_and_groups', methods: ['GET'])]
    public function getSharablePeopleAndGroups(ConversationRepository $conversationRepository, UserRepository $userRepository, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $name = $request->query->get('name');
        $conversations = $conversationRepository->findConversations($user->getId(), $name, "true", 1, 5);
        $users = $userRepository->searchUsers($name, true, $user->getId(), 1, 5, 'friendship');

        return $this->json(
            ['conversations' => $conversations, 'users' => $users],
            JsonResponse::HTTP_OK,
            ['groups' => ['user:read:light', "conversations:read:light"]]
        );
    }

    #[Route('/conversation/edit', name: 'edit_conversation', methods: ['POST'])]
    public function editConversation(ConversationRepository $conversationRepository, Request $request, SerializerInterface $serializer, EntityManagerInterface $em): JsonResponse
    {
        $errors = [];
        $editedConversation = $serializer->deserialize($request->getContent(), Conversation::class, 'json');
        $conversation = $conversationRepository->findOneBy(['id' => $editedConversation->getId()]);
        if (!$conversation) {
            $errors[] = 'conversation_not_found';
        } else if ($conversation->getAdministrator()->getId() !== $this->getUser()->getId()) {
            $errors[]  = 'you_are_not_admin';
        }
        if ($errors === []) {
            $conversation->setName($editedConversation->getName());
            $conversation->setPrivate($editedConversation->isPrivate());
            $conversation->setSport($editedConversation->getSport());
            $conversation->setLat($editedConversation->getLat());
            $conversation->setLon($editedConversation->getLon());
            $conversation->setLocationName($editedConversation->getLocationName());
            $em->flush();
        }

        return $this->json(
            ['errors' => $errors],
            $errors === [] ?   JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN
        );
    }

    #[Route('/conversation/setNewAdmin', name: 'set_new_conversation_admin', methods: ['GET'])]
    public function setNewAdmin(ConversationService $conversationService, ConversationRepository $conversationRepository, UserRepository $userRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $requestQuery = $request->query;
        $user = $this->getUser();
        $conversation = $conversationRepository->getConversation($user->getId(), $requestQuery->get('conversationId'));
        $errors = $conversationService->isAdmin($user, $conversation);

        if ($errors === []) {
            $newAdmin = $userRepository->findOneBy(['id' => $requestQuery->get('userId')]);
            if ($newAdmin) {
                $conversation->removeMember($newAdmin);
                $conversation->setAdministrator($newAdmin);
                $conversation->addMember($user);
            } else {
                $errors[] = 'user_not_found';
            }
            if ($errors === []) {
                $em->flush();
            }
        }

        return $this->json(
            ['errors' => $errors],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN
        );
    }

    #[Route('/conversations/upcomingSessions', name: 'get_conversation_upcoming_sessions', methods: ['GET'])]
    public function upcomingSessions(SessionRepository $sessionRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $sessions = $sessionRepository->upcomingSessionsInConversation($this->getUser()->getId(), $requestQuery->get('now'), $requestQuery->get('conversationId'));

        return $this->json(
            ['sessions' => $sessions],
            JsonResponse::HTTP_OK,
            ['groups' => ['collection:sessions']]
        );
    }

    #[Route('/conversation/editNotifications', name: 'disable_conversation_notifications', methods: ['POST'])]
    public function disableNotifications(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $jsonRequest = json_decode($request->getContent());
        $errors = [];

        $this->getUser()->setDisabledConversationNotifications($jsonRequest->conversations);
        $em->flush();

        return $this->json(
            ['errors' => $errors],
            JsonResponse::HTTP_OK,
            ['groups' => ['collection:sessions']]
        );
    }
}
