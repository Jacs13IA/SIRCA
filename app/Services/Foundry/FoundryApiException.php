<?php

namespace App\Services\Foundry;

class FoundryApiException extends FoundryFailure
{
    public readonly int $statusCode;

    public function __construct(int $statusCode, ?string $authenticationMethod = null, ?string $requestId = null)
    {
        $this->statusCode = $statusCode;

        $context = ['http_status' => $statusCode];

        if ($authenticationMethod !== null) {
            $context['authentication_method'] = $authenticationMethod;
        }

        if ($requestId !== null) {
            $context['request_id'] = $requestId;
        }

        parent::__construct('http', 'foundry_http_'.$statusCode, 'Foundry request failed.', $context);
    }
}