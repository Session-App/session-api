<?php

namespace App\Controller;

use DateTime;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\ApiBaseController;
use App\Entity\Friendship;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use App\Service\EmailService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

class AuthController extends ApiBaseController
{
    #[Route('/register', name: 'user.register', methods: ['POST'])]
    public function register(UserPasswordHasherInterface $passwordHasher, Request $request, UserRepository $userRepository, EntityManagerInterface $em): JsonResponse
    {
        $errors = [];
        $jsonData = json_decode($request->getContent());
        $userIP = $request->getClientIp();
        $ipDetails = json_decode(file_get_contents("http://ip-api.com/json/{$userIP}"));
        $referrer = null;
        if ($userRepository->findOneBy(['username' => $jsonData->username]) != null) {
            $errors[] = "already_registered_username";
        }
        $checkExistingEmail = $userRepository->findOneBy(['email' => $jsonData->email]);
        if ($checkExistingEmail != null) {
            $errors[] = "already_registered_email";
        }
        if (!filter_var($jsonData->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "invalid_email_address";
        }
        if (strlen($jsonData->username) > 22) {
            $errors[] = "username_too_long";
        }

        if ($jsonData->referredBy != null) {
            $referrer = $userRepository->findOneBy(["username" => $jsonData->referredBy]);
            if ($referrer == null) {
                $errors[] = "invalid_referral_code";
            }
        }
        if (count($errors) == 0) {
            $user = new User();
            $user->setEmail($jsonData->email);
            $user->setUsername($jsonData->username);
            $user->setProfilePicture("pp-default.svg");
            $user->setContribution(0);
            $user->setCreatedAt(new DateTime());
            $user->setPlatform($jsonData->platform ?? null);
            $user->setVersion($jsonData->version ?? 0);
            $user->setLang($jsonData->lang ?? null);
            $user->setDisabledConversationNotifications([]);
            $user->setLoggedOut(true);
            $user->setNewsletterSubscribed(true);
            $user->setReferredBy($referrer);
            $user->setTricksLearning([]);
            $user->setTricksMastered([]);
            $user->setSportXP([]);
            $user->setMasteredTags([]);
            $password = $passwordHasher->hashPassword($user, $jsonData->password);
            $user->setPassword($password);
            if ($referrer != null) {
                $referrer->increaseContribution(150);
                $friendship = new Friendship();
                $friendship->setRequester($referrer);
                $friendship->setRequested($user);
                $friendship->setAcceptedAt(new DateTime());
                $em->persist($friendship);
            }
            if ($ipDetails->status === 'success') {
                $user->setLastLocationLat($ipDetails->lat);
                $user->setLastLocationLon($ipDetails->lon);
            }
            $em->persist($user);
            $em->flush();
            $result = $user;
        } else {
            $result = 'registration_failed';
        }

        return $this->json(
            [
                'result' => $result,
                'errors' => $errors,
            ],
            $errors === [] ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST,
            ["groups" => ["user:read"]]
        );
    }

    #[Route('/after_login_info', methods: ["GET"])]
    public function afterLogin(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json(
            [
                'blockedUsers' => $user->getBlockedUsers(),
                'tricksMastered' => $user->getTricksMastered(),
                'tricksLearning' => $user->getTricksLearning(),
                'sportXP' => $user->getSportXP(),
                'masteredTags' => $user->getMasteredTags(),

            ],
            Response::HTTP_OK,
            ['groups' => 'user:read:light']
        );
    }

    #[Route('/change_password', methods: ["POST"])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): JsonResponse
    {
        $jsonData = json_decode($request->getContent());
        $user = $this->getUser();
        $plaintextPassword = $jsonData->password;

        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $plaintextPassword
        );

        $user->setPassword($hashedPassword);
        $em->flush();


        return $this->json(
            [
                'result' => true,
            ],
            Response::HTTP_OK,
        );
    }

    #[Route('/forgotten_password', methods: ['POST'])]
    public function forgottenPassword(
        Request $request,
        UserRepository $userRepository,
        EmailService $emailService,
        EntityManagerInterface $em
    ): JsonResponse {
        $email = json_decode($request->getContent())->email;
        $user = null;
        $errors = [];

        if (!empty($email)) {
            $user = $userRepository->findOneBy(['email' => $email]);
        } else {
            $errors[] = 'no_email_provided';
        }

        if (null !== $user) {
            $token = hash('sha512', uniqid('password_request', true));
            $user->setPasswordResetToken($token);
            $user->setPasswordResetRequestedAt(new DateTimeImmutable());
            $em->flush();

            $emailService->send($user, ['user' => $user, 'email_name' => 'password_reset']);
        } else {
            $errors[] = 'email_not_found';
        }

        return $this->json(
            [
                'errors' => $errors,
                'result' => $errors === [],
            ],
            $errors === [] ? Response::HTTP_OK : Response::HTTP_NOT_FOUND,
        );
    }

    #[Route('/web/reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        EmailService $emailService
    ): JsonResponse {
        //todo : send email password changed successfully
        $jsonRequest = json_decode($request->getContent());
        $token = $jsonRequest->token;
        $password = $jsonRequest->password;
        $errors = [];
        $user = null;

        if (empty($token) || empty($password)) {
            $errors[] = 'invalid_token';
        } else {
            $user = $userRepository->findOneBy(['id' => $jsonRequest->userId]);
            if ($user === null) {
                $errors[] = 'user_not_found';
            }
            if ($user->getPasswordResetToken() !== $token || $user->hasPasswordResetTokenExpired()) {
                $errors[] = 'invalid_token';
            }
        }

        if ($errors === [] && $user !== null) {
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setPasswordResetToken(null);
            $user->setPasswordResetRequestedAt(null);
            $em->flush();
        }

        return $this->json(
            [
                'result' => $errors === [],
                'errors' => $errors
            ],
            $errors === [] ? Response::HTTP_OK : Response::HTTP_FORBIDDEN,
        );
    }

    #[Route('/logout', methods: ["GET"])]
    public function logout(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $user->setLoggedOut(true);
        $em->flush();

        return $this->json(
            [
                'result' => true,
            ],
            Response::HTTP_OK,
        );
    }

    #[Route('/delete_account')]
    public function deleteAccount(EntityManagerInterface $em): JsonResponse
    {
        $this->getUser()->setDeletedAt(new DateTimeImmutable());
        // $em->remove($this->getUser());
        $em->flush();

        return $this->json(
            [
                'result' => true,
            ],
            Response::HTTP_OK,
        );
    }
}
