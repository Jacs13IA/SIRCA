<?php

namespace App\Services\Foundry;

use Composer\CaBundle\CaBundle;
use Illuminate\Support\Facades\Http;
use Throwable;

class FoundryApiClient
{
    public function __construct(private readonly FoundryTokenProvider $tokens) {}

    public function createConversation(): string
    {
        $payload = $this->request('POST', $this->agentPath().'/conversations', ['items' => []]);
        $conversationId = $payload['id'] ?? null;

        if (! is_string($conversationId) || $conversationId === '') {
            throw new FoundryFailure('conversation', 'conversation_id_missing', 'Foundry did not return a conversation identifier.');
        }

        return $conversationId;
    }

    public function createResponse(string $conversationId, array $input): array
    {
        return $this->request('POST', $this->agentPath().'/responses', [
            'conversation' => $conversationId,
            'input' => $input,
        ]);
    }

    private function request(string $method, string $path, ?array $payload): array
    {
        $endpoint = config('services.foundry.project_endpoint');
        $version = config('services.foundry.api_version', 'v1');

        if (! is_string($endpoint) || ! filter_var($endpoint, FILTER_VALIDATE_URL)
            || parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new FoundryFailure('configuration', 'project_endpoint_invalid', 'Foundry project endpoint is missing or invalid.');
        }

        $url = rtrim($endpoint, '/').$path.'?api-version='.rawurlencode((string) $version);
        $token = $this->tokens->getToken();
        $authenticationMethod = $this->tokens->authenticationMethod();
        $request = Http::acceptJson()
            ->withToken($token)
            ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
            ->timeout((int) config('services.foundry.timeout', 45));

        if ($payload !== null) {
            $request->asJson();
        }

        try {
            $response = $request->send($method, $url, $payload === null ? [] : ['json' => $payload]);
        } catch (Throwable) {
            throw new FoundryFailure('transport', 'foundry_unreachable', 'Foundry request could not be completed.');
        }

        if ($response->failed()) {
            $requestId = $response->header('x-ms-request-id');
            $requestId = is_string($requestId) && preg_match('/\A[a-zA-Z0-9._:-]{1,128}\z/', $requestId)
                ? $requestId
                : null;

            throw new FoundryApiException($response->status(), $authenticationMethod, $requestId);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new FoundryFailure('response', 'foundry_response_invalid', 'Foundry returned an invalid response.');
        }

        return $data;
    }

    private function agentPath(): string
    {
        $agentName = config('services.foundry.agent_name');

        if (! is_string($agentName) || trim($agentName) === '') {
            throw new FoundryFailure('configuration', 'agent_name_missing', 'Foundry agent name is not configured.');
        }

        return '/agents/'.rawurlencode($agentName).'/endpoint/protocols/openai';
    }
}