<?php

namespace App\Controller;

use App\Controller\ApiBaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Entity\Spot;
use App\Entity\CommentSpot;
use Symfony\Component\Serializer\SerializerInterface;
use Datetimeimmutable;
use App\Repository\SpotRepository;
use function json_decode;
use function json_encode;

class CommentSpotController extends ApiBaseController
{
    #[Route('/spot/comment/new', name:"api_comment_post", methods:["POST"])]
    public function post(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        SpotRepository $spotRepository,
    ): JsonResponse {
        $comment = $serializer->deserialize($request->getContent(), CommentSpot::class, 'json');
        $comment->setCreatedAt(new DateTimeImmutable());
        $spotId = json_decode($request->getContent())->spot->id;
        $spot = $spotRepository->findOneBy(['id'=>$spotId]);
        $comment->setSpot($spot);
        $comment->setAddedBy($this->getUser());
        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->json(
            [
                 'comment' => $comment,
             ],
            Response::HTTP_CREATED,
            ['groups' => ['item:spot', 'user:read:light']]
        );
    }
}
