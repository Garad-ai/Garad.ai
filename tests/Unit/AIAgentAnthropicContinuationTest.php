<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Engine\Services\AnthropicService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AIAgent\System\Services\AIAgentChatService;
use App\Services\Stream\StreamService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request as HttpRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AIAgentAnthropicContinuationTest extends TestCase
{
    #[Test]
    public function it_continues_anthropic_agent_responses_that_reach_the_output_limit(): void
    {
        $anthropic = new class ([
            ['content' => [['type' => 'text', 'text' => 'Part one']], 'stop_reason' => 'max_tokens'],
            ['content' => [['type' => 'text', 'text' => ' and the conclusion.']], 'stop_reason' => 'end_turn'],
        ]) extends AnthropicService {
            public array $requestedMessages = [];

            public function __construct(private array $responses) {}

            public function stream(): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
            {
                $this->requestedMessages[] = $this->getMessages();

                return new Response(new PsrResponse(
                    200,
                    [],
                    json_encode(array_shift($this->responses), JSON_THROW_ON_ERROR),
                ));
            }
        };
        $this->app->instance(AnthropicService::class, $anthropic);

        $response = app(AIAgentChatService::class)->chat(
            'Write the requested report.',
            [['role' => 'user', 'content' => 'Write the full report.']],
            EngineEnum::ANTHROPIC,
            EntityEnum::CLAUDE_HAIKU_4_5,
        );

        self::assertSame('Part one and the conclusion.', $response);
        self::assertCount(2, $anthropic->requestedMessages);
        self::assertSame('Part one', $anthropic->requestedMessages[1][1]['content']);
        self::assertSame('user', $anthropic->requestedMessages[1][2]['role']);
    }

    #[Test]
    public function it_does_not_report_an_incomplete_anthropic_agent_response_as_complete(): void
    {
        $responses = array_fill(0, 5, [
            'content' => [['type' => 'text', 'text' => 'More text']],
            'stop_reason' => 'max_tokens',
        ]);
        $anthropic = new class ($responses) extends AnthropicService {
            public function __construct(private array $responses) {}

            public function stream(): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
            {
                return new Response(new PsrResponse(
                    200,
                    [],
                    json_encode(array_shift($this->responses), JSON_THROW_ON_ERROR),
                ));
            }
        };
        $this->app->instance(AnthropicService::class, $anthropic);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The response is incomplete');

        app(AIAgentChatService::class)->chat(
            'Write the requested report.',
            [['role' => 'user', 'content' => 'Write the full report.']],
            EngineEnum::ANTHROPIC,
            EntityEnum::CLAUDE_HAIKU_4_5,
        );
    }

    #[Test]
    public function it_continues_streamed_anthropic_text_without_repeating_the_first_segment(): void
    {
        $anthropic = new class ([
            "data: {\"type\":\"content_block_delta\",\"delta\":{\"text\":\" and the conclusion.\"}}\n"
            . "data: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\"}}\n"
            . "data: {\"type\":\"message_stop\"}\n",
        ]) extends AnthropicService {
            public array $requestedMessages = [];

            public function __construct(private array $streams) {}

            public function stream(): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
            {
                $this->requestedMessages[] = $this->getMessages();

                return new Response(new PsrResponse(200, [], array_shift($this->streams)));
            }
        };
        $anthropic->setKey('test-key');
        $request = HttpRequest::create('/');
        $this->app->instance('request', $request);
        $serviceClass = new \ReflectionClass(StreamService::class);
        $service = $serviceClass->newInstanceWithoutConstructor();
        $continue = $serviceClass->getMethod('continueAnthropicTextResponse');
        $continue->setAccessible(true);

        $stopReason = 'max_tokens';
        $responseText = 'Part one';
        $output = '';
        $tokens = 0;
        ob_start();
        ob_start();

        try {
            $completed = $continue->invokeArgs($service, [
                $anthropic,
                'Write the report.',
                [['role' => 'user', 'content' => 'Write the full report.']],
                false,
                &$stopReason,
                &$responseText,
                &$output,
                &$tokens,
            ]);
        } finally {
            ob_end_clean();
            ob_end_clean();
        }

        self::assertTrue($completed);
        self::assertSame('Part one and the conclusion.', $responseText);
        self::assertSame('end_turn', $stopReason);
        self::assertSame('Part one', $anthropic->requestedMessages[0][1]['content']);
    }
}
