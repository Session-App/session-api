<?php

namespace App\Controller;

use App\Repository\FriendshipRepository;
use Aws\S3\S3Client;
use App\Repository\UserRepository;
use Aws\Exception\AwsException;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\ApiBaseController;
use App\Entity\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\SessionRepository;
use App\Repository\SpotRepository;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Json;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Repository\SecretVerificationRepository;
use DateTime;
use DoctrineExtensions\Query\Mysql\Date;

class UserController extends ApiBaseController
{
    public function __construct(
        public SerializerInterface $serializer
    ) {
    }

    #[Route('/profile_picture_link', methods: ["POST"])]
    public function getProfilePictureLink(Request $request): JsonResponse
    {
        $region =  'eu-west-3';
        $bucketName = "session-app";
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY']
            ]
        ]);

        $pictureName = json_decode($request->getContent())->name;

        $cmd = $s3->getCommand('PutObject', [
            'Bucket' => $bucketName,
            'Key'    =>  "profile_pictures/" . $pictureName
        ]);

        $uploadURL = $s3->createPresignedRequest($cmd, '+60 seconds');
        $presignedUrl = (string) $uploadURL->getUri();

        return $this->json([
            'url' => $presignedUrl
        ], 200);
    }

    #[Route('/save_profile_picture', methods: ["POST"])]
    public function saveProfilePicture(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $jsonData = json_decode($request->getContent());
        $pictureName = $jsonData->name;
        $user = $this->getUser();
        $user->setProfilePicture($jsonData->name);

        $entityManager->flush();

        return $this->json(
            [
                "result" => "success"
            ],
            201
        );
    }

    #[Route('/profile', methods: ["GET"])]
    public function profile(
        SessionRepository $sessionRepository,
        SpotRepository $spotRepository,
        UserRepository $userRepository,
        secretVerificationRepository $secretVerificationRepository,
        Request $request
    ): Response {
        $user = $this->getUser();
        $userId = $user->getId();
        $query = $request->query;
        /*$organizedSessions = $sessionRepository->amountOfOrganizedSessions($userId, $query->get('now'));*/
        $passedSessions = $sessionRepository->amountOfPassedSessions($userId, $query->get('now'));
        $upcomingSessions = $sessionRepository->amountOfUpcomingSessions($userId, $query->get('now'));
        $addedSpots = $spotRepository->amountOfAddedSpots($userId);
        $favoriteSpots = $userRepository->amountOfFavoriteSpots($userId);
        $contribution = $user->getContribution();
        $verifications = [];
        foreach ($user->getFavoriteSports() as $favoriteSport) {
            if (in_array($favoriteSport, $this->getParameter('app.verifiable_sports'))) {
                if ($this->isGranted('ROLE_SECRET_' . $favoriteSport)) {
                    $verifications[$favoriteSport] = 5;
                } else {
                    $verifications[$favoriteSport] = $secretVerificationRepository->getVerifiedAmount($user->getId(), $favoriteSport);
                }
            }
        }

        return $this->json(
            ['user' => $user, 'stats' => [
                /*'organizedSessions' => $organizedSessions,*/
                'passedSessions' => $passedSessions,
                'upcomingSessions' => $upcomingSessions,
                'addedSpots' => $addedSpots,
                'favoriteSpots' => $favoriteSpots,
                'contribution' => $contribution,
                'verifications' => $verifications
            ]],
            Response::HTTP_OK,
            ["groups" => ["user:read", "user:read:private"]]
        );
    }

    #[Route('/foreignProfile/{userId}', methods: ["GET"])]
    public function foreignProfile(
        SessionRepository $sessionRepository,
        SpotRepository $spotRepository,
        UserRepository $userRepository,
        FriendshipRepository $friendshipRepository,
        int $userId,
        secretVerificationRepository $secretVerificationRepository,
        Request $request
    ): Response {
        $user = $userRepository->findOneBy(['id' => $userId]);
        $query = $request->query;
        $upcomingSessions = $sessionRepository->amountOfUpcomingSessions($userId, $query->get('now'));
        /*$organizedSessions = $sessionRepository->amountOfOrganizedSessions($userId, $query->get('now'));*/
        $passedSessions = $sessionRepository->amountOfPassedSessions($userId, $query->get('now'));
        $addedSpots = $spotRepository->amountOfAddedSpots($userId);

        $alreadyVerifiedSports = [];
        if (!empty(array_intersect($this->getUser()->getFavoriteSports(), $this->getParameter('app.verifiable_sports')))) {
            $alreadyVerifiedSports = $secretVerificationRepository->findBy(['verified' => $userId, 'verifier' => $this->getUser()->getId()]);
        }

        $favoriteSpots = $userRepository->amountOfFavoriteSpots($userId);
        $contribution = $user->getContribution();
        $isFriend = $friendshipRepository->isFriend($userId, $this->getUser()->getId());

        return $this->json(
            [
                'user' => $user,
                'stats' => [
                    /*'organizedSessions'=>$organizedSessions,*/
                    'passedSessions' => $passedSessions,
                    'upcomingSessions' => $upcomingSessions,
                    'addedSpots' => $addedSpots,
                    'favoriteSpots' => $favoriteSpots,
                    'contribution' => $contribution
                ],
                'alreadyVerifiedSports' => $alreadyVerifiedSports,
                'isFriend' => $isFriend
            ],
            Response::HTTP_OK,
            ["groups" => ["user:read"]]
        );
    }

    #[Route('/profile/upcomingSessions', methods: ["GET"])]
    public function upcomingSessions(
        SessionRepository $sessionRepository,
        FriendshipRepository $friendshipRepository,
        Request $request
    ): Response {
        $user = $this->getUser();
        $userId = $user->getId();
        $query = $request->query;
        $askedUserId = $query->get('userId');
        if ($userId == $askedUserId || $friendshipRepository->isFriend($askedUserId, $userId)) {
            $upcomingSessions = $sessionRepository->userUpcomingSessions($askedUserId, $query->get('now'), $query->get('page'), 3);
            $result = true;
        } else {
            $result = false;
        }

        return $this->json(
            $result ? ['sessions' => $upcomingSessions] : "user_not_friend",
            $result ? Response::HTTP_OK : Response::HTTP_FORBIDDEN,
            ["groups" => ["collection:sessions", "min:spot"]]
        );
    }

    #[Route('/profile/passedSessions', methods: ["GET"])]
    public function organizedSessions(
        SessionRepository $sessionRepository,
        FriendshipRepository $friendshipRepository,
        Request $request
    ): Response {
        $user = $this->getUser();
        $userId = $user->getId();
        $query = $request->query;
        $askedUserId = $query->get('userId');
        if ($userId == $askedUserId || $friendshipRepository->isFriend($askedUserId, $userId)) {
            $organizedSessions = $sessionRepository->userPassedSessions($askedUserId, $query->get('now'), $query->get('page'), 3);
            $result = true;
        } else {
            $result = false;
        }

        return $this->json(
            $result ? ['sessions' => $organizedSessions] : "user_not_friend",
            $result ? Response::HTTP_OK : Response::HTTP_FORBIDDEN,
            ["groups" => ["collection:sessions", "min:spot"]]
        );
    }

    /*

    #[Route('/profile/joinedSessions', methods:["GET"])]
        public function joinedSessions(
            SessionRepository $sessionRepository,
            FriendshipRepository $friendshipRepository,
            Request $request
        ): Response {
            $user = $this->getUser();
            $userId = $user->getId();
            $query = $request->query;
            $askedUserId = $query->get('userId');
            if ($userId == $askedUserId || $friendshipRepository->isFriend($askedUserId, $userId)) {
    $joinedSessions = $sessionRepository->userjoinedSessions($askedUserId, $query->get('now'), $query->get('page'), 3);
    $result = true;
            } else {
    $result = false;
            }

            return $this->json(
    $result ? ['sessions' => $joinedSessions] : "user_not_friend",
    $result ? Response::HTTP_OK : Response::HTTP_FORBIDDEN,
    ["groups"=>["collection:sessions", "min:spot"]]
            );
        }

     */

    #[Route('/profile/addedSpots', methods: ["GET"])]
    public function addedSpots(
        SpotRepository $spotRepository,
        FriendshipRepository $friendshipRepository,
        Request $request
    ): Response {
        $user = $this->getUser();
        $userId = $user->getId();
        $query = $request->query;
        $askedUserId = $query->get('userId');
        if ($userId == $askedUserId || $friendshipRepository->isFriend($askedUserId, $userId)) {
            $addedSpots = $spotRepository->userAddedSpots($askedUserId, $query->get('page'), 5);
            $result = true;
        } else {
            $result = false;
        }

        return $this->json(
            $result ? ['spots' => $addedSpots] : "user_not_friend",
            $result ? Response::HTTP_OK : Response::HTTP_FORBIDDEN,
            ["groups" => ["collection:spots", "item:spot"]]
        );
    }

    #[Route('/profile/favoriteSpots', methods: ["GET"])]
    public function getFavoriteSpots(
        SpotRepository $spotRepository,
        UserRepository $userRepository,
        FriendshipRepository $friendshipRepository,
        Request $request
    ): Response {
        $user = $this->getUser();
        $userId = $user->getId();
        $query = $request->query;
        $askedUserId = $query->get('userId');

        if ($userId == $askedUserId || $friendshipRepository->isFriend($askedUserId, $userId)) {
            $favoriteSpots = $spotRepository->userFavoriteSpots($askedUserId, $query->get('page'), 5);
            $result = true;
        } else {
            $result = false;
        }


        //dd($favoriteSpots);
        //$favoriteSpots = $userRepository->userFavoriteSpots($userId, $query->get('page'), 5);

        return $this->json(
            $result ? ['spots' => $favoriteSpots] : "user_not_friend",
            $result ? Response::HTTP_OK : Response::HTTP_FORBIDDEN,
            ["groups" => ["collection:spots", "item:spot"]]
        );
    }

    #[Route('/spot/favoriteSpot', methods: ["POST"])]
    public function setFavoriteSpot(
        UserRepository $userRepository,
        SpotRepository $spotRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $user = $this->getUser();
        $jsonRequest = json_decode($request->getContent());

        if ($jsonRequest->action == 'add') {
            $user->addFavoriteSpot($spotRepository->findOneBy(['id' => $jsonRequest->spotId]));
        } elseif ($jsonRequest->action == 'remove') {
            $user->removeFavoriteSpot($spotRepository->findOneBy(['id' => $jsonRequest->spotId]));
        }

        $entityManager->flush();

        return $this->json(
            'success',
            Response::HTTP_OK
        );
    }

    #[Route('/profile/edit', methods: ["POST"])]
    public function editProfile(
        EntityManagerInterface $entityManager,
        Request $request
    ): JsonResponse {
        $user = $this->getUser();
        $jsonRequest = json_decode($request->getContent());
        if ($jsonRequest->bio != null) {
            $user->setBio($jsonRequest->bio);
        }
        $user->setFavoriteSports($jsonRequest->favoriteSports);
        $entityManager->flush();

        return $this->json(
            "success",
            200
        );
    }

    // #[Route('/profile/progression', methods: ["POST"])]
    // public function editProgression(
    //     EntityManagerInterface $entityManager,
    //     Request $request
    // ): JsonResponse {
    //     $jsonRequest = json_decode($request->getContent());
    //     $user = $this->getUser();
    //     $userProgression = $user->getProgression();

    //     if (!isset($userProgression[$jsonRequest->sportId])) {
    //         $userProgression[$jsonRequest->sportId] = [];
    //     }
    //     if (!in_array($jsonRequest->skill, $userProgression[$jsonRequest->sportId])) {
    //         $userProgression[$jsonRequest->sportId][] = $jsonRequest->skill;
    //         $result = "success";
    //     } else {
    //         $result = "skill_already_mastered";
    //     }
    //     $user->setProgression($userProgression);
    //     $entityManager->flush();

    //     return $this->json(
    //         $result,
    //         $result !== "success" ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK
    //     );
    // }

    #[Route('/users/search', methods: ["GET"])]
    public function findUsers(
        UserRepository $userRepository,
        Request $request
    ): JsonResponse {
        $requestQuery = $request->query;
        $username = $requestQuery->get('username');
        $hideMe = $requestQuery->get('hideMe') === "true";
        $users = [];
        if ($username != "") {
            $users = $userRepository->searchUsers($username, $hideMe, $this->getUser()->getId(), 1, 10);
        }

        return $this->json(
            $users,
            200,
            ["groups" => ["user:read:light"]]
        );
    }

    #[Route('/profile/notificationsToken', methods: ["POST"])]
    public function setPushNotificationsToken(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        $jsonRequest = json_decode($request->getContent());

        $this->getUser()->setPushNotificationsToken($jsonRequest->firebaseToken);

        $em->flush();

        return $this->json(
            'success',
            200
        );
    }

    #[Route('/profile/setLang', methods: ["POST"])]
    public function setLang(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        $lang = json_decode($request->getContent())->lang;

        $this->getUser()->setLang($lang);

        $em->flush();

        return $this->json(
            'success',
            200
        );
    }

    //collects data about last connection's informations
    #[Route('/profile/setLastLocation', methods: ["POST"])]
    public function setLastLocation(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        $jsonRequest = json_decode($request->getContent());
        $user = $this->getUser();
        $connection = new Connection();
        $connection->setDate(new DateTime());
        $connection->setUser($user);
        $em->persist($connection);

        if ($jsonRequest->lat) {
            $user->setLastLocationLat($jsonRequest->lat);
            $user->setLastLocationLon($jsonRequest->lon);
        }
        if ($jsonRequest->platform) {
            $user->setVersion($jsonRequest->version ?? 0);
            $user->setPlatform($jsonRequest->platform);
        }
        $user->setLoggedOut(false);
        // $user->setLastConnection(new DateTime());

        $em->flush();

        return $this->json(
            'success',
            200
        );
    }

    #[Route('/web/unsubscribe_newsletter', methods: ["POST"])]
    public function unsubscribeNewsletter(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse {

        $errors = [];
        $jsonRequest = json_decode($request->getContent());

        $user = $userRepository->findOneBy(['id' => $jsonRequest->userId]);

        if ($user->getNewsletterToken() !== $jsonRequest->token) {
            $errors[] = 'invalid_token';
        }
        if ($errors === []) {
            $user->setNewsletterSubscribed(false);
            $em->flush();
        }

        return $this->json(
            ['errors' => $errors],
            200
        );
    }

    //when a user is blocked, he is added in the database, and on the frontend, he is hidden from the conversations list (if a conversation with him/her exists) and notifications for this conversations are not sent anymore
    #[Route('/user/block/{userId}', methods: ["GET"])]
    public function blockUser(
        UserRepository $userRepository,
        EntityManagerInterface $em,
        ConversationRepository $conversationRepository,
        int $userId
    ): JsonResponse {
        $errors = [];
        $user = $userRepository->findOneBy(['id' => $userId]);

        if (!$user) {
            $errors[] = 'user_not_found';
        }
        if ($errors === []) {
            $this->getUser()->addBlockedUser($user);
            $conversation = $conversationRepository->doesConversationExist([$userId], $this->getUser()->getId(), null);
            if ($conversation) {
                $disabledNotifications = $this->getUser()->getDisabledConversationNotifications();
                $disabledNotifications[] = $conversation->getId();
                $this->getUser()->setDisabledConversationNotifications($disabledNotifications);
            }
            $em->flush();
        }

        return $this->json(
            ['errors' => $errors],
            $errors === [] ? Response::HTTP_OK : Response::HTTP_NOT_FOUND
        );
    }
}
