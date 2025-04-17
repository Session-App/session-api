<?php

namespace App\Metrics\Controller;

use App\Controller\ApiBaseController;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NewsLetterController extends ApiBaseController
{
    #[Route('/newsletter', name: 'send_newsletter', methods: ['POST'])]
    public function usersActivity(UserRepository $userRepository, EmailService $emailService, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $password = 'qkdkjsqdf465qsd641!!684qe5cqsdfaqseez6(-à&';
        $errors = [];

        $jsonRequest = json_decode($request->getContent());

        if ($jsonRequest->password !== $password) {
            $errors[] = 'wrong password';
        }
        if ($this->isGranted('ROLE_NEWSLETTER', $this->getUser())) {
            $errors[] = 'not allowed';
        }

        if ($errors === []) {

            // $users = $userRepository->getUsersWithoutRefreshToken();
            // dd($users);
            $users = $userRepository->findAll();
            // $users = [$this->getUser()];
            // dd($users);
            foreach ($users as $user) {
                $token = hash('sha512', uniqid('newsletter', true));
                //todo :only if token doesn't already exist
                $user->setNewsletterToken($token);
                if ($user->isSubscribedNewsletter() /*&& $user->getId() > 886*/) {
                    dump($user->getId());
                    try {
                        $emailService->send($user, ['user' => $user, 'email_name' => 'newsletter', 'token' => $token, 'userId' => $user->getId()]);
                    } catch (Exception $e) {
                        dump($e);
                    }
                }
            }
            $em->flush();
        }


        return $this->json(
            [
                'errors' => $errors
            ],
            JsonResponse::HTTP_OK
        );
    }
}
