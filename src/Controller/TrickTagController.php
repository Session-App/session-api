<?php

namespace App\Controller;

use App\Entity\Trick;
use App\Entity\TrickTag;
use App\Repository\TrickRepository;
use App\Repository\TrickTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class TrickTagController extends ApiBaseController
{
    #[Route('/trickTags', name: 'get_trick_tags', methods: ['GET'])]
    public function getTags(Request $request, TrickTagRepository $tagRepository): JsonResponse
    {
        $errors = [];
        $tags = [];
        $requestQuery = $request->query;

        if (count($errors) === 0) {
            $tags = $tagRepository->getTags($requestQuery->get('sportId'));
        }

        return $this->json(
            ['errors' => $errors, 'tags' => $tags],
            $errors !== [] ? JsonResponse::HTTP_FORBIDDEN : JsonResponse::HTTP_OK,
            ['groups' => ['trickTags:read']]
        );
    }

    #[Route('/trickTag', name: 'new_trick_tag', methods: ['POST'])]
    public function newTrick(SerializerInterface $serializer, EntityManagerInterface $em, Request $request): JsonResponse
    {
        $errors = [];

        if (!$this->isGranted('ROLE_TRICKS_ADD')) {
            $errors[] = 'not_allowed_to_add_tricks';
        }
        if (count($errors) === 0) {
            $tag = $serializer->deserialize($request->getContent(), TrickTag::class, 'json');

            $em->persist($tag);
            $em->flush();
        }

        return $this->json(
            ['errors' => $errors],
            $errors !== [] ? JsonResponse::HTTP_FORBIDDEN : JsonResponse::HTTP_OK,
            ['groups' => ['trick:read', 'trick:collection']]
        );
    }
}
