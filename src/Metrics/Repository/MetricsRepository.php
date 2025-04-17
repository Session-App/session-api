<?php

namespace App\Metrics\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MetricsRepository
{
    protected $em;
    protected TranslatorInterface $translator;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator
    ) {
        $this->em = $em;
        $this->translator = $translator;
    }
}
