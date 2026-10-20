<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AiChatProEntityHighlight\System\Services\EntityDetailService;
use App\Extensions\AiChatProEntityHighlight\System\Services\EntityHighlightService;
use ReflectionMethod;
use Tests\TestCase;

final class EntityHighlightServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai-chat-pro-entity-highlight.supported_entity_types' => ['person', 'organization', 'place']]);
        \Illuminate\Support\Facades\Schema::create('app_settings', function ($table) {
            $table->increments('id');
            $table->string('key')->index();
            $table->text('value');
        });
        \Illuminate\Support\Facades\DB::table('app_settings')->insert([
            ['key' => 'ai_chat_pro_entity_highlight_max', 'value' => '3'],
            ['key' => 'ai_chat_pro_entity_highlight_threshold', 'value' => '0.8'],
        ]);
    }

    private function parse(string $output): array
    {
        return EntityHighlightService::parseAnnotations($output);
    }

    public function test_only_entities_present_in_the_visible_answer_are_returned(): void
    {
        $output = "Ada Lovelace worked with Charles Babbage.\n\n:::meta\n"
            . '{"entities":[{"text":"Ada Lovelace","type":"person","confidence":0.95,"occurrence_index":0,"context_snippet":"Ada"},'
            . '{"text":"Invented Person","type":"person","confidence":0.99,"occurrence_index":0,"context_snippet":"x"}]}'
            . "\n:::";

        $entities = $this->parse($output);

        self::assertSame(['Ada Lovelace'], array_column($entities, 'text'));
    }

    public function test_low_confidence_unsupported_type_and_duplicate_entities_are_rejected(): void
    {
        $output = "Garad AI and garad ai met Ada Lovelace in Hargeisa.\n\n:::meta\n"
            . '{"entities":['
            . '{"text":"Garad AI","type":"organization","confidence":0.95,"occurrence_index":0,"context_snippet":"a"},'
            . '{"text":"garad ai","type":"organization","confidence":0.95,"occurrence_index":0,"context_snippet":"a"},'
            . '{"text":"Ada Lovelace","type":"person","confidence":0.2,"occurrence_index":0,"context_snippet":"a"},'
            . '{"text":"Hargeisa","type":"spaceship","confidence":0.95,"occurrence_index":0,"context_snippet":"a"}'
            . "]}\n:::";

        $entities = $this->parse($output);

        self::assertSame(['Garad AI'], array_column($entities, 'text'));
    }

    public function test_malformed_metadata_yields_no_entities_and_is_stripped_from_the_answer(): void
    {
        $output = "Plain answer.\n\n:::meta\n{not json\n:::";

        self::assertSame([], $this->parse($output));
        self::assertSame('Plain answer.', EntityHighlightService::stripAnnotationBlock($output));
    }

    public function test_failed_detail_lookup_is_flagged_unavailable_instead_of_presented_as_details(): void
    {
        $method = new ReflectionMethod(EntityDetailService::class, 'parseDetailResponse');
        $method->setAccessible(true);

        $failed = $method->invoke(null, '{}');
        $garbled = $method->invoke(null, 'not json');
        $valid = $method->invoke(null, json_encode([
            'title' => 'Ada Lovelace',
            'description' => 'Mathematician.',
            'key_facts' => ['Born 1815'],
        ]));

        self::assertTrue($failed['unavailable']);
        self::assertTrue($garbled['unavailable']);
        self::assertArrayNotHasKey('unavailable', $valid);
        self::assertSame(['Born 1815'], $valid['key_facts']);
        self::assertSame([], $valid['sections']);
    }
}
