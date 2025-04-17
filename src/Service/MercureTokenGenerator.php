<?php

namespace App\Service;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

class MercureTokenGenerator
{
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function generateToken(array $topics): string
    {
        // dd($topics, $this->secret);
        $token = (new Builder(new JoseEncoder(), ChainedFormatter::default()))
            ->withClaim('mercure', ['subscribe' => $topics])
            ->getToken(new Sha256(), InMemory::plainText($this->secret));

        return $token->toString();
    }
}
