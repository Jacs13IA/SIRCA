<?php

namespace App\Services\Chat;

use App\Services\Foundry\FoundryApiClient;
use App\Services\Foundry\FoundryFailure;
use App\Services\Foundry\FoundryResponseParser;
use App\Services\StagedEvidenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChatConversationService
{
    private const SESSION_KEY = 'chat.foundry';

    public function __construct(
        private readonly FoundryApiClient $foundry,
        private readonly FoundryResponseParser $parser,
        private readonly StagedEvidenceService $evidence,
    ) {}

    public function send(string $message, ?float $latitude = null, ?float $longitude = null): array
    {
        $state = $this->state();
        $input = [['role' => 'user', 'content' => $message]];

        if ($latitude !== null && $longitude !== null) {
            $input[] = $this->platformEvent('citizen_location', [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        }

        $pendingReference = $this->pendingEvidenceReference($state);
        $includeEvidence = $pendingReference !== null && ! ($state['pending_evidence']['sent'] ?? false);

        if ($includeEvidence) {
            $input[] = $this->platformEvent('evidence_reference', ['reference' => $pendingReference]);
        }

        return $this->dispatch($state, $input, $includeEvidence);
    }

    public function stageEvidence(UploadedFile $file): array
    {
        $state = $this->state();

        if (($state['evidence_requested'] ?? false) !== true) {
            throw new AuthorizationException('El agente todavía no ha solicitado evidencia.');
        }

        if (($reference = $this->pendingEvidenceReference($state)) !== null
            && $this->evidence->isAvailable($reference)) {
            throw ValidationException::withMessages([
                'evidence' => 'Ya hay evidencia pendiente en esta conversación.',
            ]);
        }

        $staged = $this->evidence->stage($file);
        $state['evidence_requested'] = false;
        $state['pending_evidence'] = [
            'reference' => Crypt::encryptString($staged['reference']),
            'expires_at' => $staged['expires_at'],
            'sent' => false,
        ];
        $this->persistState($state);

        try {
            $result = $this->sendPlatformEvidence($state, $staged['reference']);
        } catch (Throwable $exception) {
            $context = ['exception' => $exception::class];

            if ($exception instanceof FoundryFailure) {
                $context += [
                    'stage' => $exception->stage,
                    'failure_code' => $exception->failureCode,
                ] + $exception->safeContext;
            }

            $logLevel = $exception instanceof FoundryFailure ? 'warning' : 'error';
            Log::{$logLevel}('Foundry did not acknowledge staged evidence.', $context);

            return [
                'message' => null,
                'ui_actions' => [],
                'evidence_requested' => false,
                'evidence_pending' => true,
                'delivery_pending' => true,
            ];
        }

        return [...$result, 'evidence_pending' => true];
    }

    private function sendPlatformEvidence(array $state, string $reference): array
    {
        return $this->dispatch(
            $state,
            [$this->platformEvent('evidence_reference', ['reference' => $reference])],
            true,
        );
    }

    private function dispatch(array $state, array $input, bool $includeEvidence): array
    {
        $conversationId = $state['conversation_id'] ?? null;

        if (! is_string($conversationId) || $conversationId === '') {
            $conversationId = $this->foundry->createConversation();
            $state['conversation_id'] = $conversationId;
            $this->persistState($state);
        }

        $response = $this->foundry->createResponse($conversationId, $input);
        $result = $this->parser->parse($response);
        $pendingReference = $this->pendingEvidenceReference($state);

        if ($pendingReference !== null) {
            $result['message'] = str_replace($pendingReference, '[referencia protegida]', $result['message']);
        }

        $state['evidence_requested'] = in_array('request_evidence', $result['ui_actions'], true);

        if ($includeEvidence && is_array($state['pending_evidence'] ?? null)) {
            $state['pending_evidence']['sent'] = true;
        }

        if (is_array($state['pending_evidence'] ?? null)) {
            $reference = $this->pendingEvidenceReference($state);

            if ($reference === null || ! $this->evidence->isAvailable($reference)) {
                unset($state['pending_evidence']);
            }
        }

        $this->persistState($state);

        return [
            'message' => $result['message'],
            'ui_actions' => $result['ui_actions'],
            'evidence_requested' => $state['evidence_requested'],
            'evidence_pending' => isset($state['pending_evidence']),
            'delivery_pending' => false,
        ];
    }

    private function state(): array
    {
        try {
            return session()->get(self::SESSION_KEY, []);
        } catch (QueryException $exception) {
            Log::warning('Citizen chat session state could not be loaded.', ['exception' => $exception::class]);

            return [];
        }
    }

    private function persistState(array $state): void
    {
        try {
            session()->put(self::SESSION_KEY, $state);
        } catch (QueryException $exception) {
            Log::warning('Citizen chat session state could not be persisted.', ['exception' => $exception::class]);
        }
    }

    private function pendingEvidenceReference(array &$state): ?string
    {
        $pending = $state['pending_evidence'] ?? null;

        if (! is_array($pending) || ! is_string($pending['reference'] ?? null)) {
            return null;
        }

        if (! is_string($pending['expires_at'] ?? null) || now()->greaterThanOrEqualTo($pending['expires_at'])) {
            unset($state['pending_evidence']);

            return null;
        }

        try {
            return Crypt::decryptString($pending['reference']);
        } catch (Throwable) {
            unset($state['pending_evidence']);

            return null;
        }
    }

    private function platformEvent(string $type, array $data): array
    {
        return [
            'role' => 'user',
            'content' => '[SIRCA_PLATFORM_EVENT] '.json_encode([
                'type' => $type,
                'data' => $data,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }
}