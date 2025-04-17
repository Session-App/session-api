<?php

namespace App\Controller;

use App\Controller\ApiBaseController;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Entity\Spot;
use App\Entity\Picture;
use Symfony\Component\Serializer\SerializerInterface;
use Datetimeimmutable;
use App\Repository\SpotRepository;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class PictureController extends ApiBaseController
{
    #[Route('/picture_link/{sportId}', methods:["POST"])]
    public function getPictureLink(
        Request $request,
        EntityManagerInterface $entityManager,
        $sportId,
    ): JsonResponse {
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

        $pictureName =json_decode($request->getContent())->name;

        $cmd = $s3->getCommand('PutObject', [
                    'Bucket' => $bucketName,
                    'Key'    =>  "spots_".$sportId."/".$pictureName
                ]);

        $uploadURL = $s3->createPresignedRequest($cmd, '+60 seconds');
        $presignedUrl = (string) $uploadURL->getUri();

        return $this->json(
            [
            "url" => $presignedUrl
                ],
            201
        );
    }

    #[Route('/save_picture', methods:["POST"])]
    public function savePicture(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        SpotRepository $spotRepository,
    ): JsonResponse {
        $json_data = json_decode($request->getContent());
        $pictureName =$json_data->name;
        $spotId = $json_data->spotId;
        $user = $this->getUser();

        $picture = $serializer->deserialize($request->getContent(), Picture::class, 'json');
        $spot = $spotRepository->findOneBy(['id'=>$spotId]);
        $picture->setSpot($spot);
        $picture->setAddedBy($user);
        $picture->setCreatedAt(new DateTime());

        $user->increaseContribution(30);

        $entityManager->persist($picture);
        $entityManager->flush();

        return $this->json(
            [
            "result" => "success"
                ],
            201
        );
    }
}
