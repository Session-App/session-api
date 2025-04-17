<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

abstract class ApiBaseController extends AbstractController
{
    public SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param array $data
     * @param int $status
     * @param array $headers
     * @param array $context
     * @return JsonResponse
     */
    protected function json($data, int $status = 200, array $context = [], array $headers = []): JsonResponse
    {
        //$context['json_encode_options'] = JsonResponse::DEFAULT_ENCODING_OPTIONS;

        $json = $this->serializer->serialize($data, 'json', $context);

        return new JsonResponse(
            $json,
            $status,
            $headers,
            true
        );
    }

    public function jsonNotFound(string $message = 'Not found'): JsonResponse
    {
        return $this->json([
            'result' => false,
            'errors' => [
                $message
            ]
        ], Response::HTTP_NOT_FOUND);
    }
}
