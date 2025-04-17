<?php

namespace App\Controller;

use App\Entity\ReportedEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use Symfony\Component\Serializer\SerializerInterface;

class ReportedEntityController extends ApiBaseController
{
    #[Route('/report', methods: ["POST"], name: 'report_entity')]
    public function reportEntity(SerializerInterface $serializer, EntityManagerInterface $em, Request $request, UserRepository $userRepository): Response
    {
        $reportedEntity = $serializer->deserialize($request->getContent(), ReportedEntity::class, 'json');
        $reportedEntity->setReportedAt(new DateTimeImmutable());
        $reportedEntity->setReportedBy($this->getUser());

        $em->persist($reportedEntity);
        $em->flush();

        return $this->json(
            ['result' => true],
            Response::HTTP_OK
        );
    }
}
