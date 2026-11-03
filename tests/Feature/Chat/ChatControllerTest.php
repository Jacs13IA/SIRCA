<?php

namespace Tests\Feature\Chat;

use App\Services\Foundry\FoundryApiClient;
use App\Services\Foundry\FoundryApiException;
use App\Services\Foundry\FoundryFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_chat_uses_a_server_session_and_returns_structured_agent_actions(): void
    {
        $foundry = Mockery::mock(FoundryApiClient::class);
        $foundry->shouldReceive('createConversation')->once()->andReturn('conv_private_123');
        $foundry->shouldReceive('createResponse')
            ->once()
            ->with('conv_private_123', [['role' => 'user', 'content' => 'Necesito reportar una luminaria.']])
            ->andReturn($this->agentResponse('¿Tienes una fotografía?', ['request_evidence']));
        $this->app->instance(FoundryApiClient::class, $foundry);

        $this->postJson('/chat/messages', ['message' => 'Necesito reportar una luminaria.'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', '¿Tienes una fotografía?')
            ->assertJsonPath('data.ui_actions.0', 'request_evidence')
            ->assertJsonMissingPath('data.conversation_id');

        $this->assertSame('conv_private_123', session('chat.foundry.conversation_id'));
        $this->assertTrue(session('chat.foundry.evidence_requested'));
    }

    public function test_evidence_is_forbidden_until_the_agent_requests_it(): void
    {
        $this->post('/evidence-staging', [
            'evidence' => UploadedFile::fake()->image('luminaria.jpg'),
        ])->assertForbidden();

        $this->assertDatabaseCount('staged_evidences', 0);
    }

    public function test_requested_evidence_is_staged_privately_and_never_returned_to_the_browser(): void
    {
        $foundry = Mockery::mock(FoundryApiClient::class);
        $foundry->shouldReceive('createConversation')->once()->andReturn('conv_private_456');
        $foundry->shouldReceive('createResponse')
            ->once()
            ->with('conv_private_456', [['role' => 'user', 'content' => 'El poste está apagado.']])
            ->andReturn($this->agentResponse('Adjunta una fotografía si puedes.', ['request_evidence']));
        $foundry->shouldReceive('createResponse')
            ->once()
            ->withArgs(function (string $conversationId, array $input): bool {
                return $conversationId === 'conv_private_456'
                    && ($input[0]['role'] ?? null) === 'user'
                    && str_starts_with($input[0]['content'] ?? '', '[SIRCA_PLATFORM_EVENT] ')
                    && json_decode(substr($input[0]['content'], strlen('[SIRCA_PLATFORM_EVENT] ')), true)['type'] === 'evidence_reference';
            })
            ->andReturnUsing(function (string $conversationId, array $input): array {
                $event = json_decode(substr($input[0]['content'], strlen('[SIRCA_PLATFORM_EVENT] ')), true);

                return $this->agentResponse('La referencia es '.$event['data']['reference'], []);
            });
        $this->app->instance(FoundryApiClient::class, $foundry);

        $this->postJson('/chat/messages', ['message' => 'El poste está apagado.'])->assertOk();

        $response = $this->post('/evidence-staging', [
            'evidence' => UploadedFile::fake()->image('luminaria.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.reference');

        $reference = Crypt::decryptString(session('chat.foundry.pending_evidence.reference'));
        $this->assertStringNotContainsString($reference, $response->getContent());
        $this->assertStringNotContainsString($reference, (string) $response->json('data.message'));
        $this->assertTrue(app(\App\Services\StagedEvidenceService::class)->isAvailable($reference));
    }

    public function test_location_is_forwarded_as_platform_context_after_explicit_submission(): void
    {
        $foundry = Mockery::mock(FoundryApiClient::class);
        $foundry->shouldReceive('createConversation')->once()->andReturn('conv_location_123');
        $foundry->shouldReceive('createResponse')
            ->once()
            ->withArgs(function (string $conversationId, array $input): bool {
                $locationEvent = $input[1]['content'] ?? '';

                return $conversationId === 'conv_location_123'
                    && str_contains($locationEvent, '"type":"citizen_location"')
                    && str_contains($locationEvent, '19.35')
                    && str_contains($locationEvent, '-90.72');
            })
            ->andReturn($this->agentResponse('Gracias, recibí la ubicación.', []));
        $this->app->instance(FoundryApiClient::class, $foundry);

        $this->postJson('/chat/messages', [
            'message' => 'Te comparto mi ubicación actual.',
            'latitude' => 19.35,
            'longitude' => -90.72,
        ])->assertOk();
    }

    public function test_staged_evidence_is_kept_for_the_next_turn_when_foundry_is_unavailable(): void
    {
        $foundry = Mockery::mock(FoundryApiClient::class);
        $foundry->shouldReceive('createConversation')->once()->andReturn('conv_retry_123');
        $foundry->shouldReceive('createResponse')
            ->once()
            ->andReturn($this->agentResponse('Adjunta una fotografía.', ['request_evidence']));
        $foundry->shouldReceive('createResponse')
            ->once()
            ->andThrow(new \RuntimeException('Temporary upstream failure'));
        $this->app->instance(FoundryApiClient::class, $foundry);

        $this->postJson('/chat/messages', ['message' => 'Hay un cable dañado.'])->assertOk();

        $this->post('/evidence-staging', [
            'evidence' => UploadedFile::fake()->image('cable.png'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.delivery_pending', true)
            ->assertJsonPath('data.evidence_pending', true)
            ->assertJsonMissingPath('data.reference');

        $this->assertNotNull(session('chat.foundry.pending_evidence.reference'));
        $this->assertFalse(session('chat.foundry.pending_evidence.sent'));
    }

    public function test_client_cannot_choose_conversation_or_enable_evidence(): void
    {
        $this->postJson('/chat/messages', [
            'message' => 'Hola',
            'conversation_id' => 'conv_attacker_value',
            'ui_actions' => ['request_evidence'],
        ])->assertUnprocessable();
    }

    public function test_chat_logs_safe_failure_stage_without_user_content_or_exception_message(): void
    {
        $conversation = Mockery::mock(\App\Services\Chat\ChatConversationService::class);
        $conversation->shouldReceive('send')
            ->once()
            ->andThrow(new FoundryFailure('authentication', 'azure_cli_login_required', 'Sensitive exception details'));
        $this->app->instance(\App\Services\Chat\ChatConversationService::class, $conversation);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Citizen chat request failed.'
                && $context === [
                    'stage' => 'authentication',
                    'failure_code' => 'azure_cli_login_required',
                    'exception' => FoundryFailure::class,
                ]
            );

        $this->postJson('/chat/messages', ['message' => 'Private citizen message'])
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_chat_logs_safe_foundry_http_diagnostics(): void
    {
        $conversation = Mockery::mock(\App\Services\Chat\ChatConversationService::class);
        $conversation->shouldReceive('send')
            ->once()
            ->andThrow(new FoundryApiException(403, 'managed_identity', 'request-123'));
        $this->app->instance(\App\Services\Chat\ChatConversationService::class, $conversation);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Citizen chat request failed.'
                && $context === [
                    'stage' => 'http',
                    'failure_code' => 'foundry_http_403',
                    'exception' => FoundryApiException::class,
                    'http_status' => 403,
                    'authentication_method' => 'managed_identity',
                    'request_id' => 'request-123',
                ]
            );

        $this->postJson('/chat/messages', ['message' => 'Private citizen message'])
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_unexpected_chat_failures_are_logged_as_errors_without_exception_details(): void
    {
        $conversation = Mockery::mock(\App\Services\Chat\ChatConversationService::class);
        $conversation->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Sensitive upstream details'));
        $this->app->instance(\App\Services\Chat\ChatConversationService::class, $conversation);

        Log::shouldReceive('warning')->never();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Citizen chat request failed.'
                && $context === [
                    'stage' => 'unknown',
                    'failure_code' => 'unexpected',
                    'exception' => \RuntimeException::class,
                ]
            );

        $this->postJson('/chat/messages', ['message' => 'Private citizen message'])
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    private function agentResponse(string $message, array $actions): array
    {
        return [
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'message' => $message,
                        'ui_actions' => $actions,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
        ];
    }
}