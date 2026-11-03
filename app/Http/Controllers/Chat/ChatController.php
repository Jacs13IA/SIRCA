<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Requests\StoreStagedEvidenceRequest;
use App\Services\Chat\ChatConversationService;
use App\Services\Foundry\FoundryFailure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChatController extends Controller
{
    public function send(SendChatMessageRequest $request, ChatConversationService $conversation): JsonResponse
    {
        try {
            $result = $conversation->send(
                $request->validated('message'),
                $request->validated('latitude'),
                $request->validated('longitude'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Respuesta recibida.',
                'data' => $result,
            ]);
        } catch (Throwable $exception) {
            $context = [
                'stage' => $exception instanceof FoundryFailure ? $exception->stage : 'unknown',
                'failure_code' => $exception instanceof FoundryFailure ? $exception->failureCode : 'unexpected',
                'exception' => $exception::class,
            ];

            if ($exception instanceof FoundryFailure) {
                $context += $exception->safeContext;
            }

            $logLevel = $exception instanceof FoundryFailure ? 'warning' : 'error';
            Log::{$logLevel}('Citizen chat request failed.', $context);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener una respuesta en este momento. Tu mensaje sigue disponible para volver a intentarlo.',
                'data' => null,
            ], 502);
        }
    }

    public function storeEvidence(StoreStagedEvidenceRequest $request, ChatConversationService $conversation): JsonResponse
    {
        try {
            $result = $conversation->stageEvidence($request->file('evidence'));

            return response()->json([
                'success' => true,
                'message' => $result['evidence_pending']
                    ? 'La evidencia quedó disponible para el agente.'
                    : 'La evidencia fue recibida.',
                'data' => $result,
            ], 201);
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $context = ['exception' => $exception::class];

            if ($exception instanceof FoundryFailure) {
                $context += [
                    'stage' => $exception->stage,
                    'failure_code' => $exception->failureCode,
                ] + $exception->safeContext;
            }

            $logLevel = $exception instanceof FoundryFailure ? 'warning' : 'error';
            Log::{$logLevel}('Citizen chat evidence upload failed.', $context);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible procesar la evidencia. Inténtalo de nuevo.',
                'data' => null,
            ], 502);
        }
    }
}