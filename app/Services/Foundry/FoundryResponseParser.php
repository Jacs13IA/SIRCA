<?php

namespace App\Services\Foundry;

class FoundryResponseParser
{
    private const ALLOWED_UI_ACTIONS = ['request_evidence'];

    public function parse(array $response): array
    {
        if (($response['status'] ?? null) !== 'completed' || ($response['error'] ?? null) !== null) {
            throw new FoundryFailure('response', 'agent_response_incomplete', 'Foundry did not complete the response.');
        }

        $text = $this->extractText($response);

        if ($text === '') {
            throw new FoundryFailure('response', 'agent_response_empty', 'Foundry response did not include assistant text.');
        }

        $envelope = json_decode($text, true);

        if (! is_array($envelope) || ! is_string($envelope['message'] ?? null)) {
            return ['message' => $text, 'ui_actions' => []];
        }

        $actions = $envelope['ui_actions'] ?? [];

        if (! is_array($actions)) {
            $actions = [];
        }

        return [
            'message' => $envelope['message'],
            'ui_actions' => array_values(array_unique(array_filter(
                $actions,
                fn (mixed $action): bool => is_string($action) && in_array($action, self::ALLOWED_UI_ACTIONS, true),
            ))),
        ];
    }

    private function extractText(array $response): string
    {
        $parts = [];

        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message' || ($item['role'] ?? null) !== 'assistant') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (in_array($content['type'] ?? null, ['output_text', 'text'], true)
                    && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }

        if ($parts === [] && is_string($response['output_text'] ?? null)) {
            $parts[] = $response['output_text'];
        }

        return trim(implode("\n", $parts));
    }
}