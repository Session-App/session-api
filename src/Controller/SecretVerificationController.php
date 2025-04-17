<?php

namespace App\Controller;

use App\Controller\ApiBaseController;
use App\Repository\SecretVerificationRepository;
use App\Repository\UserRepository;
use App\Entity\secretVerification;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class SecretVerificationController extends ApiBaseController
{
    #[Route('/secretVerification/verify/{sportId}', name: 'verify_user', methods:["POST"])]
    public function verifyUser(int $sportId, UserRepository $userRepository, SecretVerificationRepository $secretVerificationRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $jsonRequest = json_decode($request->getContent());
        $forbidden = false;
        $user= $this->getUser();
        $toBeVerifiedUser = $userRepository->findOneBy(['id' => $jsonRequest->userId]);

        if (!$this->isGranted('ROLE_SECRET_' . $sportId, $user)) {
            $forbidden = true;
            $message = 'not_allowed_verify_users_this_sport';
        } elseif ($toBeVerifiedUser->getId() == $user->getId()) {
            $forbidden = true;
            $message = 'you_cant_verify_yourself';
        } elseif ($secretVerificationRepository->verificationExists($jsonRequest->userId, $user, $sportId) == 1) {
            $forbidden = true;
            $message = 'user_already_verified_for_this_sport';
        }

        if ($forbidden) {
            return $this->json(
                [
                   'error' => 'Forbidden',
                   'message' => $message
                  ],
                Response::HTTP_FORBIDDEN
            );
        }

        $verifiedAmount = $secretVerificationRepository->getVerifiedAmount($jsonRequest->userId, $sportId);

        if ($verifiedAmount == 4) {
            $roles = $toBeVerifiedUser->getRoles();
            array_push($roles, 'ROLE_SECRET_' . $sportId);
            $toBeVerifiedUser->setRoles($roles);
        }

        $verification = new secretVerification();
        $verification->setVerifier($user);
        $verification->setVerified($toBeVerifiedUser);
        $verification->setSport($sportId);
        $verification->setVerifiedAt(new DateTime());

        $em->persist($verification);
        $em->flush();

        return $this->json(
            [
                    'result' => 'success'
                ],
            Response::HTTP_OK
        );
    }
}
