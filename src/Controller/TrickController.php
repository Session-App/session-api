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

class TrickController extends ApiBaseController
{
    #[Route('/tricks', name: 'get_tricks', methods: ['GET'])]
    public function getTricks(TrickRepository $trickRepository, Request $request): JsonResponse
    {
        $requestQuery = $request->query;
        $tricks = $trickRepository->getTricks($requestQuery->get('sportId'), $requestQuery->get('tagId'), $requestQuery->get('page'), $requestQuery->get('size'), $requestQuery->get('name'), $this->getUser()->getLang() ?? 'en');
        // dd($tricks);

        return $this->json(
            ['tricks' => $tricks],
            JsonResponse::HTTP_OK,
            ['groups' => ['trick:collection']]
        );
    }

    #[Route('/trick', name: 'get_trick', methods: ['GET'])]
    public function getTrick(TrickRepository $trickRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $locale = $this->getUser()->getLang() ?? 'en';
        $trick = $trickRepository->getTrick($request->query->get('trickId'), $locale);
        $variationsAmount = $trickRepository->getTrickVariationsAmount($request->query->get('trickId'));

        return $this->json(
            ['trick' => $trick, 'variationsAmount' => $variationsAmount],
            $trick ? JsonResponse::HTTP_OK : JsonResponse::HTTP_NOT_FOUND,
            ['groups' => ['trick:read']]
        );
    }

    #[Route('/trick/variations', name: 'get_trick_variations', methods: ['GET'])]
    public function getTrickVariations(TrickRepository $trickRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $locale = $this->getUser()->getLang() ?? 'en';
        $tricks = $trickRepository->getVariations($request->query->get('trickId'), $locale);

        return $this->json(
            ['tricks' => $tricks],
            $tricks ? JsonResponse::HTTP_OK : JsonResponse::HTTP_NOT_FOUND,
            ['groups' => ['trick:collection']]
        );
    }

    #[Route('/trick/master', name: 'master_trick', methods: ['GET'])]
    public function masteredTrick(TrickRepository $trickRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $errors = [];
        $requestQuery = $request->query;
        $user = $this->getUser();
        $trick = $trickRepository->findOneBy(['id' => $requestQuery->get('trickId')]);

        if (!$trick) {
            $errors[] = 'trick_not_found';
        } else if ($user->isTrickMastered($trick->getId())) {
            $errors[] = 'trick_already_mastered';
        }
        if ($errors === []) {
            $user->addTrickMastered($trick->getId());
            $user->increaseSportXP($trick->getPoints(), $trick->getSport());
            $masteredTags = $user->getMasteredTags();
            if (!isset($masteredTags[$trick->getSport()])) {
                $masteredTags[$trick->getSport()] = [];
            }
            foreach ($trick->getTags() as $tag) {
                if (!isset($masteredTags[$trick->getSport()][$tag->getId()])) {
                    $masteredTags[$trick->getSport()][$tag->getId()] = 1;
                } else {
                    $masteredTags[$trick->getSport()][$tag->getId()]++;
                }
            }
            $user->setMasteredTags($masteredTags);
            $trick->increaseAmountMastered(1);

            if ($user->isLearningTrick($trick->getId())) {
                $user->removeTrickLearning($trick->getId());
                $trick->increaseAmountLearning(-1);
            }

            $em->flush();
        }

        return $this->json(
            [
                'errors' => $errors,
                'tricksMastered' => $user->getTricksMastered(),
                'tricksLearning' => $user->getTricksLearning(),
                'sportXP' => $user->getSportXP(),
                'masteredTags' => $user->getMasteredTags(),
                'amountLearning' => $trick->getAmountLearning(),
                'amountMastered' => $trick->getAmountMastered()
            ],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN
        );
    }

    #[Route('/trick/unmaster', name: 'unmaster_trick', methods: ['GET'])]
    public function unMasteredTrick(TrickRepository $trickRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $errors = [];
        $requestQuery = $request->query;
        $user = $this->getUser();
        $trick = $trickRepository->findOneBy(['id' => $requestQuery->get('trickId')]);

        if (!$trick) {
            $errors[] = 'trick_not_found';
        } else if (!$user->isTrickMastered($trick->getId())) {
            $errors[] = 'trick_not_mastered';
        }
        if ($errors === []) {
            $user->removeTrickMastered($trick->getId());
            $user->increaseSportXP(-$trick->getPoints(), $trick->getSport());
            $masteredTags = $user->getMasteredTags();
            foreach ($trick->getTags() as $tag) {
                $masteredTags[$trick->getSport()][$tag->getId()]--;
            }
            $user->setMasteredTags($masteredTags);
            $trick->increaseAmountMastered(-1);
            $em->flush();
        }

        return $this->json(
            [
                'errors' => $errors,
                'tricksMastered' => $user->getTricksMastered(),
                'tricksLearning' => $user->getTricksLearning(),
                'sportXP' => $user->getSportXP(),
                'masteredTags' => $user->getMasteredTags(),
                'amountLearning' => $trick->getAmountLearning(),
                'amountMastered' => $trick->getAmountMastered()
            ],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN
        );
    }

    #[Route('/trick/learn', name: 'learn_trick', methods: ['GET'])]
    public function learningTrick(TrickRepository $trickRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $errors = [];
        $requestQuery = $request->query;
        $user = $this->getUser();
        $trick = $trickRepository->findOneBy(['id' => $requestQuery->get('trickId')]);

        if (!$trick) {
            $errors[] = 'trick_not_found';
        } else if ($user->isTrickMastered($trick->getId())) {
            $errors[] = 'trick_already_mastered';
        } else if ($user->isLearningTrick($trick->getId())) {
            $errors[] = 'trick_already_learning';
        }
        if ($errors === []) {
            $user->addTrickLearning($trick->getId());
            $trick->increaseAmountLearning(1);

            $em->flush();
        }

        return $this->json(
            ['errors' => $errors, 'tricksLearning' => $user->getTricksLearning(), 'amountLearning' => $trick->getAmountLearning()],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_BAD_REQUEST
        );
    }

    #[Route('/trick/unlearn', name: 'unlearn_trick', methods: ['GET'])]
    public function unlearnTrick(TrickRepository $trickRepository, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $errors = [];
        $requestQuery = $request->query;
        $user = $this->getUser();
        $trick = $trickRepository->findOneBy(['id' => $requestQuery->get('trickId')]);

        if (!$trick) {
            $errors[] = 'trick_not_found';
        } else if ($user->isTrickMastered($trick->getId())) {
            $errors[] = 'trick_already_mastered';
        } else if (!$user->isLearningTrick($trick->getId())) {
            $errors[] = 'not_learning_trick';
        }
        if ($errors === []) {
            $user->removeTrickLearning($trick->getId());
            $trick->increaseAmountLearning(-1);

            $em->flush();
        }

        return $this->json(
            ['errors' => $errors, 'tricksLearning' => $user->getTricksLearning(), 'amountLearning' => $trick->getAmountLearning()],
            $errors === [] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN
        );
    }

    #[Route('/trick', name: 'new_trick', methods: ['POST'])]
    public function newTrick(EntityManagerInterface $em, Request $request, TrickRepository $trickRepository, TrickTagRepository $tagsRepository): JsonResponse
    {
        $errors = [];

        $trick = $this->serializer->deserialize($request->getContent(), Trick::class, 'json');

        $similarTrick = $trickRepository->findSimilarTrick($trick->getName(), $trick->getSport(), $trick->getVideo());
        if (!$this->isGranted('ROLE_TRICKS_ADD')) {
            $errors[] = 'not_allowed_to_add_tricks';
        }
        if ($similarTrick) {
            $errors[] = 'trick_already_exists';
        }
        if ($errors === []) {
            foreach ($trick->getTags() as $oldTag) {
                $tag = $tagsRepository->findOneBy(['id' => $oldTag->getId()]);
                $trick->removeTag($oldTag);
                $trick->addTag($tag);
            }
            if ($trick->getVariationOf() && $trick->getVariationOf()->getId()) {
                $trick->setVariationOf($trickRepository->findOneBy(['id' => $trick->getVariationOf()->getId()]));
            }
            $trick->setAmountMastered(0);
            $trick->setAmountLearning(0);
            $em->persist($trick);
            $em->flush();
        }

        return $this->json(
            ['errors' => $errors, 'similar_trick' => $similarTrick, 'sent_trick' => $trick],
            $errors !== [] ? JsonResponse::HTTP_FORBIDDEN : JsonResponse::HTTP_OK,
            ['groups' => ['trick:read', 'trick:collection']]
        );
    }

    #[Route('/trick/translate', name: 'translate_trick', methods: ['POST'])]
    public function translateTrick(EntityManagerInterface $em, Request $request, TrickRepository $trickRepository): JsonResponse
    {
        $errors = [];
        $jsonRequest = json_decode($request->getContent());

        $trick = $trickRepository->findOneBy(['id' => $jsonRequest->id]);

        if (!$this->isGranted('ROLE_TRICKS_ADD')) {
            $errors[] = 'not_allowed_to_add_tricks';
        }
        if (!$trick) {
            $errors[] = 'trick_doesnt_exist';
        }
        if ($errors === []) {
            $trick->setDescription($jsonRequest->description);
            $trick->setVideo($jsonRequest->video);
            $trick->setName($jsonRequest->name);
            $trick->setTranslatableLocale($jsonRequest->lang);
            // $repository = $em->getRepository('Gedmo\Translatable\Entity\Translation');
            // $translations = $repository->findTranslations($trick);
            // dd($translations);
            $em->persist($trick);
            $em->flush();
        }

        return $this->json(
            ['errors' => $errors],
            $errors !== [] ? JsonResponse::HTTP_FORBIDDEN : JsonResponse::HTTP_OK
        );
    }
}
