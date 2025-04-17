<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\SpotRepository;
use App\Service\AdminService;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PushNotificationsService;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route("admin/")]
class AdminController extends ApiBaseController
{
    #[Route("spots/validate", name: "get_unvalidated_spots", methods: ["GET"])]
    public function getUnvalidatedSpots(
        SpotRepository $spotRepository,
        AdminService $adminService,
    ): JsonResponse {
        $spots = null;

        if ($this->isGranted('ROLE_ADMIN_FULL')) {
            $adminSports = 'all';
        } else {
            $adminSports = $adminService->getAdminSports($this->getUser()->getRoles());
        }

        if ($adminSports !== []) {
            $spots = $spotRepository->getUnvalidatedSpots($adminSports, 1, 5);
        }

        return $this->json(
            $spots,
            $spots == null ? Response::HTTP_FORBIDDEN : Response::HTTP_OK,
            ["groups" => ["item:spot", "collection:spots", "user:read:light"]]
        );
    }

    #[Route("spots/validate/pictures", name: "get_spots_with_unvalidated_pictures", methods: ["GET"])]
    public function getSpotsWithUnvalidatedPictures(
        SpotRepository $spotRepository,
        AdminService $adminService,
    ): JsonResponse {
        $spots = null;

        if ($this->isGranted('ROLE_ADMIN_FULL')) {
            $adminSports = 'all';
        } else {
            $adminSports = $adminService->getAdminSports($this->getUser()->getRoles());
        }

        if ($adminSports !== []) {
            $spots = $spotRepository->getSpotsWithUnvalidatedPictures($adminSports, 1, 5);
        }

        return $this->json(
            $spots,
            $spots == null ? Response::HTTP_FORBIDDEN : Response::HTTP_OK,
            ["groups" => ["item:spot", "collection:spots", "user:read:light"]]
        );
    }



    #[Route("spot/validate/{sportId}", name: "validate_spot", methods: ["POST"])]
    public function validateSpot(
        SpotRepository $spotRepository,
        Request $request,
        EntityManagerInterface $em,
        PushNotificationsService $notifService,
        int $sportId
    ): JsonResponse {
        $result = false;
        $jsonRequest = json_decode($request->getContent());
        $validatedSpot = $jsonRequest->spot;
        $oldSpot = $spotRepository->findOneBy(['id' => $validatedSpot->id]);
        $isAuthorized = ($this->isGranted('ROLE_ADMIN_' . $sportId) && $oldSpot->getSport() == $sportId) || $this->isGranted('ROLE_ADMIN_FULL');

        if ($isAuthorized) {
            $oldSpot->setDescription($validatedSpot->description);
            $oldSpot->setName($validatedSpot->name);
            $oldSpot->setTags($validatedSpot->tags);
            $oldSpot->setValidated($jsonRequest->validated);
            if ($jsonRequest->secret != null) {
                $oldSpot->setSecret($jsonRequest->secret);
            }
            foreach ($oldSpot->getPictures() as $picture) {
                foreach ($validatedSpot->pictures as $vPicture) {
                    if ($vPicture->name == $picture->getName()) {
                        $picture->setValidated($vPicture->valid);
                        if (!$picture->getValidated()) {
                            $em->remove($picture);
                        }
                    }
                }
            }

            $notifData = ['spotId' => $oldSpot->getId(), 'goTo' => 'spotDetail'];
            if ($jsonRequest->onlyPictures == true) {
                foreach ($oldSpot->getPictures() as $pic) {
                    if ($pic->getValidated()) {
                        $notifService->sendNotifications([$oldSpot->getPictures()[0]->getAddedBy()], '', 'validated_pictures', ['spot_name' => $oldSpot->getName()], $notifData);
                        break;
                    }
                }
            } elseif ($jsonRequest->validated == true) {
                $notifService->sendNotifications([$oldSpot->getAddedBy()], '', 'validated_spot', ['spot_name' => $oldSpot->getName()], $notifData);
            }

            $em->flush();
            $result = 'Success';
        } elseif ($oldSpot->getValidated()) {
            $result = 'Unauthorized';
        }

        return $this->json(
            $result,
            $result == false ? Response::HTTP_FORBIDDEN : Response::HTTP_OK
        );
    }
}
