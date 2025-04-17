<?php

namespace App\Controller;

use App\Controller\ApiBaseController;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Picture;
use App\Entity\TrickMedia;
use Symfony\Component\Serializer\SerializerInterface;
use App\Repository\SpotRepository;
use App\Repository\TrickRepository;
use Aws\S3\S3Client;

class TrickMediaController extends ApiBaseController
{
    #[Route('/trick/addMedia', methods: ["GET"])]
    public function getMediaLink(
        Request $request,
    ): JsonResponse {
        $requestQuery = $request->query;
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

        //generates guid
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $mediaName = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $mediaType = $requestQuery->get("type");
        if ($mediaType === "video") {
            $mediaExtension = "mp4";
        } else if ($mediaType === "image") {
            $mediaExtension = "jpg";
        }

        $cmd = $s3->getCommand('PutObject', [
            'Bucket' => $bucketName,
            'Key'    =>  "tricks/user_uploads/" . $mediaName . "." . $mediaExtension
        ]);

        $uploadURL = $s3->createPresignedRequest($cmd, '+60 seconds');
        $presignedUrl = (string) $uploadURL->getUri();

        return $this->json(
            [
                "url" => $presignedUrl,
                "filename" => $mediaName . "." . $mediaExtension
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/trick/saveMedia', methods: ["POST"])]
    public function savePicture(
        Request $request,
        EntityManagerInterface $entityManager,
        TrickRepository $trickRepository
    ): JsonResponse {
        $jsonData = json_decode($request->getContent());
        $mediaName = $jsonData->name;
        $trickId = $jsonData->trickId;
        $user = $this->getUser();

        $media = new TrickMedia();
        $trick = $trickRepository->findOneBy(['id' => $trickId]);
        $media->setTrick($trick);
        $media->setName($mediaName);
        $media->setAddedBy($user);
        $media->setAddedAt(new DateTime());

        $user->increaseContribution(30);

        $entityManager->persist($media);
        $entityManager->flush();

        return $this->json(
            [
                "result" => "success"
            ],
            Response::HTTP_CREATED
        );
    }
}
