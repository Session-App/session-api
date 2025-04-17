<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class ConversationService
{

    public function __construct(UserRepository $userRepository, EntityManagerInterface $em)
    {
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    public function createConversation($members, $creator, $name, $private, $sport = null, $lat = null, $lon = null, $locationName = null): array
    {
        $errors = [];
        $conversation = new Conversation();
        $conversation->setPrivate($private)->setSport($sport)->setLat($lat)->setLon($lon)->setLocationName($locationName);

        if (count($members) > 1) {
            foreach ($members as $memberId) {
                $newMember = $this->userRepository->findOneBy(['id' => $memberId]);
                if ($newMember) {
                    $conversation->addMember($newMember);
                } else {
                    $errors[] = 'user_not_found';
                }
            }
        } else {
            $conversation->setRecipient($this->userRepository->findOneBy(['id' => $members[0]]));
        }

        if ($errors == []) {
            $conversation->setCreatedAt(new DateTime());
            $conversation->setAdministrator($creator);
            $conversation->setName($name);
            $this->em->persist($conversation);
            $this->em->flush();
        }

        return [$errors, $conversation];
    }

    public function isAdmin($user, $conversation): array
    {
        $errors = [];

        if ($conversation) {
            if ($conversation->getName()) {
                if ($conversation->getAdministrator()->getId() === $user->getId()) {
                } else {
                    $errors[] = 'you_are_not_admin';
                }
            } else {
                $errors[] = 'not_a_group';
            }
        } else {
            $errors[] = 'conversation_not_found';
        }

        return $errors;
    }
}
