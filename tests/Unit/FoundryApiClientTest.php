<?php

namespace Tests\Unit;

use App\Services\Foundry\FoundryApiClient;
use App\Services\Foundry\FoundryApiException;
use App\Services\Foundry\FoundryTokenProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FoundryApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.foundry.project_endpoint' => 'https://resource.services.ai.azure.com/api/projects/sirca',
            'services.foundry.agent_name' => 'CitizenAgent',
            'services.foundry.api_version' => 'v1',
            'services.foundry.timeout' => 45,
        ]);
    }

    public function test_it_creates_a_foundry_conversation_with_the_project_endpoint(): void
    {
        Http::fake([
            'resource.services.ai.azure.com/*' => Http::response(['id' => 'conv_test123'], 201),
        ]);

        $client = new FoundryApiClient($this->tokenProvider());

        $this->assertSame('conv_test123', $client->createConversation());

        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://resource.services.ai.azure.com/api/projects/sirca/agents/CitizenAgent/endpoint/protocols/openai/conversations?api-version=v1'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->data()['items'] === []
        );
    }

    public function test_it_sends_a_response_to_the_configured_agent_and_conversation(): void
    {
        Http::fake([
            'resource.services.ai.azure.com/*' => Http::response([
                'status' => 'completed',
                'output' => [],
            ]),
        ]);

        $client = new FoundryApiClient($this->tokenProvider());
        $input = [['role' => 'user', 'content' => 'Hola']];

        $client->createResponse('conv_test123', $input);

        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/agents/CitizenAgent/endpoint/protocols/openai/responses?api-version=v1')
            && $request->method() === 'POST'
            && $request->data()['conversation'] === 'conv_test123'
            && $request->data()['input'] === $input
        );
    }

    public function test_it_keeps_only_safe_diagnostic_context_for_http_failures(): void
    {
        Http::fake([
            'resource.services.ai.azure.com/*' => Http::response(
                ['error' => ['message' => 'Sensitive upstream body']],
                403,
                ['x-ms-request-id' => 'request-123'],
            ),
        ]);

        try {
            (new FoundryApiClient($this->tokenProvider('managed_identity')))->createConversation();
            $this->fail('Expected the Foundry request to fail.');
        } catch (FoundryApiException $exception) {
            $this->assertSame(403, $exception->statusCode);
            $this->assertSame([
                'http_status' => 403,
                'authentication_method' => 'managed_identity',
                'request_id' => 'request-123',
            ], $exception->safeContext);
            $this->assertStringNotContainsString('Sensitive upstream body', json_encode($exception->safeContext));
        }
    }

    private function tokenProvider(?string $authenticationMethod = null): FoundryTokenProvider
    {
        $provider = Mockery::mock(FoundryTokenProvider::class);
        $provider->shouldReceive('getToken')->andReturn('test-access-token');
        $provider->shouldReceive('authenticationMethod')->andReturn($authenticationMethod);

        return $provider;
    }
}