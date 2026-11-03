<?php

namespace Tests\Unit;

use App\Services\Foundry\FoundryResponseParser;
use RuntimeException;
use Tests\TestCase;

class FoundryResponseParserTest extends TestCase
{
    public function test_it_extracts_the_message_and_allowlisted_ui_action(): void
    {
        $parser = new FoundryResponseParser();
        $response = [
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'content' => [[
                    'type' => 'output_text',
                    'text' => '{"message":"Adjunta una fotografía si la tienes.","ui_actions":["request_evidence","delete_report"]}',
                ]],
            ]],
        ];

        $this->assertSame([
            'message' => 'Adjunta una fotografía si la tienes.',
            'ui_actions' => ['request_evidence'],
        ], $parser->parse($response));
    }

    public function test_unstructured_text_cannot_enable_evidence(): void
    {
        $parser = new FoundryResponseParser();
        $response = [
            'status' => 'completed',
            'output_text' => '¿Puedes adjuntar una fotografía?',
        ];

        $this->assertSame([
            'message' => '¿Puedes adjuntar una fotografía?',
            'ui_actions' => [],
        ], $parser->parse($response));
    }

    public function test_incomplete_response_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        (new FoundryResponseParser())->parse(['status' => 'in_progress']);
    }
}