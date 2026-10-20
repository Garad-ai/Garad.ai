<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Engine\Enums\EngineEnum;
use App\Extensions\AIChatPro\System\Services\GaradArtifactService;
use App\Services\Ai\AiCompletionService;
use App\Extensions\AIChatPro\System\Services\GaradDocumentCompositionService;
use App\Extensions\AIChatPro\System\Services\GaradDocumentGroundingService;
use Illuminate\Http\Request;
use Tests\TestCase;

final class GaradDocumentGroundingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Substantial documents require a model evidence review; this double approves the draft unchanged.
        $this->app->instance(AiCompletionService::class, new class extends AiCompletionService
        {
            public function complete(
                string $systemPrompt,
                string $userContent,
                ?EngineEnum $engine = null,
                ?string $model = null,
            ): string {
                $input = json_decode($userContent, true, 512, JSON_THROW_ON_ERROR);

                return json_encode([
                    'decision' => 'approved',
                    'content' => $input['draft_markdown'] ?? '',
                    'checks' => [
                        'requirements_covered' => true,
                        'source_facts_preserved' => true,
                        'claims_supported_or_explicitly_limited' => true,
                        'non_repetitive' => true,
                        'depth_matches_request_and_evidence' => true,
                        'language_and_structure_preserved' => true,
                    ],
                    'issues' => [],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        });
    }

    private function bindChatRequest(array $input): void
    {
        $request = Request::create('/', 'POST', $input);
        $request->attributes->set('garad_selected_model', 'gpt-4o');
        $request->attributes->set('garad_selected_engine', 'openai');
        $this->app->instance('request', $request);
    }

    public function test_it_rejects_unsupported_model_content_and_keeps_verified_source_facts(): void
    {
        $request = Request::create('/', 'POST', [
            'raw_user_input' => "This week:\n- I worked on the Arkaan website.\nMeeting:\n- I met with Baxnaano.\nPlease follow these response preferences: - Reply in English.",
        ]);
        $this->app->instance('request', $request);

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Arkaan Weekly Work Report',
            'content' => "The website is complete and publication-ready.\n\nNo blockers were encountered.",
            'summary' => 'Five actions were agreed for next week.',
            'highlights' => ['Management approved the website.'],
            'source_facts' => [
                ['section' => 'work', 'quote' => 'I worked on the Arkaan website.'],
                ['section' => 'meeting', 'quote' => 'I met with Baxnaano.'],
                ['section' => 'work', 'quote' => 'The website is complete and publication-ready.'],
            ],
        ]);

        self::assertStringContainsString('I worked on the Arkaan website.', $result['content']);
        self::assertStringContainsString('I met with Baxnaano.', $result['content']);
        self::assertStringNotContainsString('publication-ready', $result['content']);
        self::assertStringNotContainsString('No blockers', $result['content']);
        self::assertStringNotContainsString('next week', $result['content']);
        self::assertStringNotContainsString('Management approved', $result['content']);
        self::assertStringNotContainsString('response preferences', $result['content']);
        self::assertSame('passed', $result['quality_review']['status']);
        self::assertSame('Document', $result['title']);
        // The source never asks for a report, so the unsupported model-supplied report title is grounded to
        // "Document" and no report-style summary is generated.
        self::assertSame('', $result['summary']);
        self::assertSame([], $result['highlights']);
        self::assertSame(2, $result['grounding']['verified_quotes']);
    }

    public function test_it_preserves_supported_model_narrative_and_removes_unsupported_claims(): void
    {
        $source = 'Create a detailed report. I reviewed the customer guide and updated the onboarding checklist. '
            . 'The team discussed the handoff process. Do not claim approval or a launch.';
        $this->bindChatRequest(['raw_user_input' => $source]);

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Detailed Report',
            'content' => "## Executive Summary\n"
                . "The work covered a review of the customer guide and an update to the onboarding checklist.\n\n"
                . "## Meetings\nThe team discussed the handoff process. The guide was approved for launch.",
            'source_facts' => [
                ['section' => 'work', 'quote' => 'I reviewed the customer guide'],
                ['section' => 'work', 'quote' => 'updated the onboarding checklist'],
                ['section' => 'meeting', 'quote' => 'The team discussed the handoff process'],
            ],
        ]);

        self::assertStringContainsString('The work covered a review of the customer guide', $result['content']);
        self::assertStringContainsString('The team discussed the handoff process.', $result['content']);
        self::assertStringNotContainsString('approved for launch', $result['content']);
        self::assertStringNotContainsString('Additional Verified Details', $result['content']);
    }

    public function test_it_uses_the_complete_verified_composition_when_model_narrative_omits_a_fact(): void
    {
        $source = 'Create a weekly report. I reviewed the customer guide. I met with the support group.';
        $this->app->instance('request', Request::create('/', 'POST', ['raw_user_input' => $source]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Weekly Report',
            'content' => '## Work\nI reviewed the customer guide.',
            'source_facts' => [
                ['section' => 'work', 'quote' => 'I reviewed the customer guide'],
                ['section' => 'meeting', 'quote' => 'I met with the support group'],
            ],
        ]);

        self::assertStringContainsString('I reviewed the customer guide.', $result['content']);
        self::assertStringContainsString('## Meetings & Coordination', $result['content']);
        self::assertStringContainsString('I met with the support group.', $result['content']);
        self::assertStringNotContainsString('Additional Verified Details', $result['content']);
    }

    public function test_grounding_composition_removes_a_repeated_candidate_paragraph(): void
    {
        $source = 'Create a weekly report. I reviewed the client guide.';
        $this->bindChatRequest(['raw_user_input' => $source]);

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Weekly Report',
            'content' => "## Work\nI reviewed the client guide.\n\nI reviewed the client guide.",
            'source_facts' => [
                ['section' => 'work', 'quote' => 'I reviewed the client guide.'],
            ],
        ]);

        self::assertSame('passed', $result['quality_review']['status']);
        self::assertSame(1, substr_count($result['content'], 'I reviewed the client guide.'));
    }

    public function test_it_composes_a_report_overview_work_section_and_individual_meeting_cards_from_verified_facts(): void
    {
        $source = 'Create my Arkaan weekly work report. Jabir Osman Gedi is ICT Coordinator. '
            . 'This week I worked on several sections of the Arkaan website and added visual polish. '
            . 'I participated in three meetings: the Arkaan weekly main meeting with multiple agenda items and reporting; '
            . 'a meeting with Baxnaano about a proposed system for tracking work progress; '
            . 'and a meeting discussing the Garad AI launch event. Do not add dates, outcomes, or plans.';
        $this->app->instance('request', Request::create('/', 'POST', ['raw_user_input' => $source]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Arkaan Weekly Work Report',
            'content' => 'The website was completed and approved for launch.',
            'source_facts' => [
                ['section' => 'identity', 'quote' => 'Jabir Osman Gedi is ICT Coordinator'],
                ['section' => 'work', 'quote' => 'This week I worked on several sections of the Arkaan website and added visual polish.'],
                ['section' => 'meeting', 'quote' => 'the Arkaan weekly main meeting with multiple agenda items and reporting'],
                ['section' => 'meeting', 'quote' => 'a meeting with Baxnaano about a proposed system for tracking work progress'],
                ['section' => 'meeting', 'quote' => 'a meeting discussing the Garad AI launch event'],
            ],
        ]);

        self::assertSame('Jabir Osman Gedi · ICT Coordinator', $result['prepared_by']);
        self::assertSame('Weekly Overview', $result['summary_title']);
        self::assertStringContainsString('three meetings', $result['summary']);
        self::assertStringContainsString('## Website Development & Visual Improvements', $result['content']);
        self::assertStringContainsString('## Meetings & Coordination', $result['content']);
        self::assertStringNotContainsString('## Activity Log', $result['content']);
        self::assertStringNotContainsString('## Observations', $result['content']);
        self::assertStringNotContainsString('Planning & Support', $result['content']);
        self::assertStringContainsString('### Arkaan Weekly Main Meeting', $result['content']);
        self::assertStringContainsString('### Meeting with Baxnaano', $result['content']);
        self::assertStringContainsString('### Garad AI launch event', $result['content']);
        self::assertStringContainsString('The meeting covered multiple agenda items and reporting.', $result['content']);
        self::assertStringContainsString('The discussion concerned a proposed system for tracking work progress.', $result['content']);
        self::assertStringContainsString('The meeting discussed the Garad AI launch event.', $result['content']);
        self::assertStringNotContainsString('completed and approved', $result['content']);
        self::assertStringNotContainsString('dates', $result['content']);
        self::assertStringNotContainsString('next-week plan', mb_strtolower($result['content']));
        self::assertStringNotContainsString('management request', mb_strtolower($result['content']));
    }

    public function test_it_splits_a_source_verified_compound_meetings_fact_into_separate_cards(): void
    {
        $meetingFact = 'I participated in three meetings: the Arkaan weekly main meeting with multiple agenda items and reporting; '
            . 'a meeting with Baxnaano about a proposed system for tracking work progress; '
            . 'and a meeting discussing the Garad AI launch event';
        $this->app->instance('request', Request::create('/', 'POST', [
            'raw_user_input' => 'Create my Arkaan weekly work report. Jabir Osman Gedi is ICT Coordinator. '
                . 'This week I worked on several sections of the Arkaan website and added visual polish. '
                . $meetingFact . '.',
        ]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Arkaan Weekly Work Report',
            'content' => 'A model-generated report.',
            'source_facts' => [
                ['section' => 'identity', 'quote' => 'Jabir Osman Gedi is ICT Coordinator'],
                ['section' => 'work', 'quote' => 'This week I worked on several sections of the Arkaan website and added visual polish.'],
                ['section' => 'meeting', 'quote' => $meetingFact],
            ],
        ]);

        self::assertSame(
            'Work covered multiple sections of the website and included visual improvements. It also records participation in three meetings and their stated topics.',
            $result['summary'],
        );
        self::assertStringContainsString('### Arkaan Weekly Main Meeting', $result['content']);
        self::assertStringContainsString('### Meeting with Baxnaano', $result['content']);
        self::assertStringContainsString('### Garad AI launch event', $result['content']);
        self::assertSame(5, $result['grounding']['verified_quotes']);
    }

    public function test_it_does_not_repeat_work_and_meeting_facts_in_an_activity_log(): void
    {
        $facts = [
            'identity' => [],
            'work' => [
                'I prepared a compatibility summary for the browser release.',
                'I documented keyboard navigation behavior.',
            ],
            'meeting' => [
                'The weekly planning meeting where release coordination and handoff topics were discussed.',
                'A meeting with the vendor about a proposed compatibility review.',
                'A meeting discussing accessibility guidelines.',
            ],
            'other' => [],
        ];
        $source = 'Create a detailed weekly report covering the browser release, planning, vendor coordination, and accessibility.';

        $result = app(GaradDocumentCompositionService::class)->compose($facts, $source, 'Weekly Report');
        $semantic = app(\App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser::class)
            ->parseSemanticBlocks($result['content']);

        self::assertStringNotContainsString('## Activity Log', $result['content']);
        self::assertStringNotContainsString('Proposal discussed', $result['content']);
        self::assertStringContainsString('## Meetings & Coordination', $result['content']);
        self::assertStringContainsString(
            'The report brings together the reported work activities with meeting discussions',
            $result['content'],
        );
        self::assertCount(0, array_filter(
            $semantic['blocks'],
            static fn (array $block): bool => ($block['type'] ?? '') === 'table',
        ));
    }

    public function test_it_formats_somali_identity_and_does_not_count_meeting_participation_as_a_meeting(): void
    {
        $participation = 'waxaan kale ka qeeb galay ilaa 3 meeting';
        $firstMeeting = 'mid waa main meeting ka asbuuclaha ah ee arkaan oo agends kala duwan iyo reporting la isku sameeyey';
        $secondMeeting = 'midna waa waa mid aan la yeelanay baxnaano oo la rabay in loo sameeyo system lagu tala galay reporting ka ee la socoshada halka ee wax marayaan shaqooyinka';
        $thirdMeeting = 'midka kalena waa meeting loga hadlaayey daah furka xaflada event ga garad ai';
        $source = 'Create a weekly report. magaceyga waa Jabir Osman Gedi ttitle kana waa ICT Coordinator. '
            . $participation . '. ' . $firstMeeting . '. ' . $secondMeeting . '. ' . $thirdMeeting . '.';

        $result = app(GaradDocumentCompositionService::class)->compose(
            [
                'identity' => ['Jabir Osman Gedi', 'ttitle kana waa ICT Coordinator'],
                'work' => [
                    'waxaan asbucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa waxaan ku daray qurxin',
                    'waxaan kudaray qurxin',
                ],
                'meeting' => [$participation, $firstMeeting, $secondMeeting, $thirdMeeting],
                'other' => [],
            ],
            $source,
            'Weekly Report ee Arkaan',
        );

        self::assertSame('Jabir Osman Gedi · ICT Coordinator', $result['prepared_by']);
        self::assertStringContainsString('### Kulanka toddobaadlaha ah ee Arkaan', $result['content']);
        self::assertStringContainsString('### Kulanka lala yeeshay Baxnaano', $result['content']);
        self::assertStringContainsString('### Kulanka: Daahfurka xafladda Garad AI', $result['content']);
        self::assertSame(1, substr_count($result['content'], '## Horumarinta Mareegta iyo Hagaajinta Muuqaalka'));
        self::assertStringNotContainsString($participation, $result['content']);
        self::assertStringContainsString(
            'Waxaan asbuucan ka shaqeeyay qaybo kala duwan oo ka mid ah mareegta Arkaan, waxaana ku daray hagaajin muuqaal ah.',
            $result['content'],
        );
        self::assertStringContainsString(
            'Waxaa laga hadlay ajandayaal kala duwan iyo warbixin.',
            $result['content'],
        );
        self::assertStringContainsString(
            'Waxaa laga hadlay soo jeedinta nidaam loogu talagalay warbixinta iyo la socodka halka shaqooyinku marayaan.',
            $result['content'],
        );
        self::assertStringContainsString(
            'Waxaa looga hadlay daahfurka xafladda Garad AI.',
            $result['content'],
        );
        self::assertStringContainsString('qaybo kala duwan oo mareegta ah', $result['summary']);
        self::assertStringContainsString('ka qaybgalka kulamada la soo sheegay', $result['summary']);
        self::assertStringNotContainsString('Diiwaanka Hawlaha', $result['content']);
        self::assertStringNotContainsString('| Qaybta |', $result['content']);
        self::assertStringNotContainsString('la ansixiyay', $result['content']);
        self::assertSame(1, substr_count(mb_strtolower($result['content']), 'hagaajin muuqaal ah'));
        self::assertStringNotContainsString('2025', $result['content']);
    }

    public function test_it_omits_document_instructions_from_report_content(): void
    {
        $englishInstruction = 'Use the attached logo and brand color.';
        $somaliInstruction = 'logo da iyo brand geeda color adeegso';
        $somaliBrandInstruction = 'Weekly report ee arkaan adoo adeegsanaya brand ga arkaan';
        $sourceFact = 'The website work was discussed.';
        $source = 'Create a weekly report. ' . $englishInstruction . ' ' . $somaliInstruction . ' '
            . $somaliBrandInstruction . ' ' . $sourceFact;

        $result = app(GaradDocumentCompositionService::class)->compose(
            ['identity' => [], 'work' => [], 'meeting' => [], 'other' => [
                $englishInstruction,
                $somaliInstruction,
                $somaliBrandInstruction,
                $sourceFact,
            ]],
            $source,
            'Weekly Report',
        );

        self::assertStringNotContainsString($englishInstruction, $result['content']);
        self::assertStringNotContainsString($somaliInstruction, $result['content']);
        self::assertStringNotContainsString($somaliBrandInstruction, $result['content']);
        self::assertStringContainsString($sourceFact, $result['content']);
    }

    public function test_it_filters_request_fragments_from_misclassified_facts_and_identity(): void
    {
        $requestFragment = 'report shaqadeyda ee Weekly report ee arkaan';
        $source = 'sxb waxaan rabaa inaa ii sameeyso report shaqadeyda ee Weekly report ee arkaan. '
            . 'Magaceyga waa Jabir Osman Gedi ttitle kana waa ICT Coordinator. '
            . 'Waxaan asbucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa waxaan kudaray qurxin.';

        $result = app(GaradDocumentCompositionService::class)->compose(
            [
                'identity' => ['Jabir Osman Gedi', 'ICT Coordinator', 'Weekly report ee arkaan'],
                'work' => ['Waxaan asbucan ka shaqeeyay qaybo kala duwan oo ka mid ah website-ka Arkaan, waxaanan ku daray qurxin.'],
                'meeting' => [
                    'Kulanka weyn ee toddobaadlaha ah ee Arkaan; waxaa laga hadlay ajandayaal kala duwan iyo warbixin.',
                    'Kulan lala yeeshay Baxnaano oo ku saabsanaa soo jeedinta nidaam loogu talagalay warbixinta iyo la socodka halka shaqooyinku marayaan.',
                    'Kulan looga hadlay daahfurka xafladda event-ka Garad AI.',
                ],
                'other' => [$requestFragment],
            ],
            $source,
            'Weekly report ee arkaan',
            "## Faahfaahin Dheeraad ah\n\nreport shaqadeyda ee Weekly report ee arkaan",
        );

        self::assertSame('Jabir Osman Gedi · ICT Coordinator', $result['prepared_by']);
        self::assertStringNotContainsString($requestFragment, $result['content']);
        self::assertStringNotContainsString('Weekly report ee arkaan', $result['prepared_by']);
        self::assertStringNotContainsString('## Faahfaahin Dheeraad ah', $result['content']);
        self::assertStringContainsString('### Kulanka lala yeeshay Baxnaano', $result['content']);
        self::assertStringNotContainsString('## Activity Log', $result['content']);
    }

    public function test_it_uses_one_narrative_for_meetings_when_candidate_content_has_a_summary_table(): void
    {
        $facts = [
            'identity' => ['Jabir Osman Gedi', 'ICT Coordinator'],
            'work' => ['waxaan asbucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa waxaan kudaray qurxin'],
            'meeting' => [
                'mid waa main meeting ka asbuuclaha ah ee arkaan oo agends kala duwan iyo reporting la isku sameeyey',
                'midna waa waa mid aan la yeelanay baxnaano oo la rabay in loo sameeyo system lagu tala galay reporting ka ee la socoshada halka ee wax marayaan shaqooyinka',
                'midka kalena waa meeting looga hadlaayey daah furka xaflada event ga garad ai',
            ],
            'other' => [],
        ];
        $source = 'Samee warbixin toddobaadle ah. Jabir Osman Gedi waa ICT Coordinator. '
            . 'Waxaan asbuucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa waxaan kudaray qurxin. '
            . 'Waxaan kale ka qeeb galay ilaa 3 meeting: mid waa main meeting ka asbuuclaha ah ee arkaan oo agends kala duwan '
            . 'iyo reporting la isku sameeyey; midna waa waa mid aan la yeelanay baxnaano oo la rabay in loo sameeyo system lagu tala galay reporting ka ee la socoshada halka ee wax marayaan shaqooyinka; '
            . 'midka kalena waa meeting loga hadlaayey daah furka xaflada event ga garad ai.';
        $candidate = "## Activity Log\n\n| Activity | Details |\n| --- | --- |\n"
            . "| Meeting | Weekly agendas and reporting |\n\n"
            . "## Meetings\n\n### Arkaan weekly meeting\nWeekly agendas and reporting were discussed.";

        $result = app(GaradDocumentCompositionService::class)->compose(
            $facts,
            $source,
            'Warbixin Toddobaadle ah',
            $candidate,
        );

        self::assertStringNotContainsString('| Activity |', $result['content']);
        self::assertStringNotContainsString('## Activity Log', $result['content']);
        self::assertStringContainsString('ajandayaal kala duwan iyo warbixin', $result['content']);
        self::assertStringContainsString('### Kulanka toddobaadlaha ah ee Arkaan', $result['content']);
        self::assertStringContainsString('### Kulanka lala yeeshay Baxnaano', $result['content']);
        self::assertStringContainsString('### Kulanka: Daahfurka xafladda Garad AI', $result['content']);
        self::assertStringContainsString(
            'Guud ahaan, warbixintani waxay isu keentaa hawlaha mareegta iyo hagaajinta muuqaalka',
            $result['content'],
        );
        self::assertStringContainsString('daahfurka xafladda Garad AI', $result['content']);
        self::assertStringNotContainsString('Faahfaahin Dheeraad ah', $result['content']);
    }

    public function test_it_honors_an_explicit_english_request_for_somali_source_facts(): void
    {
        $source = 'sxb waxaan rabaa inaa ii sameeyso report shaqadeyda ee Weekly report ee arkaan. '
            . 'Magaceyga waa Jabir Osman Gedi ttitle kana waa ICT Coordinator. '
            . 'Waxaan asbucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa waxaan kudaray qurxin. '
            . 'waxaan kale ka qeeb galay ilaa 3 meeting: mid waa main meeting ka asbuuclaha ah ee arkaan oo agends kala duwan '
            . 'iyo reporting la isku sameeyey; midna waa waa mid aan la yeelanay baxnaano oo la rabay in loo sameeyo '
            . 'system lagu tala galay reporting ka ee la socoshada halka ee wax marayaan shaqooyinka; '
            . 'midka kalena waa meeting loga hadlaayey daah furka xaflada event ga garad ai. '
            . 'pdf ahaan igu soo sameey magaceyga waa Jabir Osman Gedi ttitle kana waa ICT Coordinator. '
            . 'logo da iyo brand geeda color adeegso english ka dhig xogta';
        $this->app->instance('request', Request::create('/', 'POST', ['raw_user_input' => $source]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Weekly report ee arkaan',
            'source_facts' => [
                ['section' => 'identity', 'quote' => 'Jabir Osman Gedi'],
                ['section' => 'identity', 'quote' => 'ICT Coordinator'],
                ['section' => 'work', 'quote' => 'Waxaan asbucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa'],
                ['section' => 'work', 'quote' => 'waxaan kudaray qurxin'],
                ['section' => 'meeting', 'quote' => 'mid waa main meeting ka asbuuclaha ah ee arkaan oo agends kala duwan iyo reporting la isku sameeyey'],
                ['section' => 'meeting', 'quote' => 'midna waa waa mid aan la yeelanay baxnaano oo la rabay in loo sameeyo system lagu tala galay reporting ka ee la socoshada halka ee wax marayaan shaqooyinka'],
                ['section' => 'meeting', 'quote' => 'midka kalena waa meeting loga hadlaayey daah furka xaflada event ga garad ai'],
            ],
        ]);

        self::assertSame('Arkaan Weekly Work Report', $result['title']);
        self::assertStringContainsString('## Website Development & Visual Improvements', $result['content']);
        self::assertStringContainsString('### Arkaan Weekly Main Meeting', $result['content']);
        self::assertStringContainsString('### meeting with baxnaano', mb_strtolower($result['content']));
        self::assertStringContainsString('### Garad AI Launch Event Discussion', $result['content']);
        self::assertStringContainsString(
            'A meeting with Baxnaano concerned a proposed system for reporting and tracking work progress.',
            $result['content'],
        );
        self::assertStringContainsString('I also added visual improvements.', $result['content']);
        self::assertStringContainsString('The report brings together website development', $result['content']);
        self::assertStringNotContainsString('report shaqadeyda ee Weekly report ee arkaan', mb_strtolower($result['content']));

        $repeatedWork = app(GaradDocumentCompositionService::class)->compose(
            [
                'identity' => [],
                'work' => [
                    'This week, I worked on multiple sections of the Arkaan website and added visual improvements.',
                    'I also added visual improvements.',
                ],
                'meeting' => ['The team discussed the work-tracking system.'],
                'other' => [],
            ],
            'Create a detailed weekly report.',
            'Weekly Report',
        );
        self::assertStringNotContainsString('I also added visual improvements.', $repeatedWork['content']);
    }

    public function test_it_keeps_non_report_documents_out_of_the_weekly_report_table_layout(): void
    {
        $source = 'Create a technical overview of the modular architecture.';
        $result = app(GaradDocumentCompositionService::class)->compose(
            [
                'identity' => [],
                'work' => ['The product uses a modular architecture.'],
                'meeting' => [],
                'other' => [],
            ],
            $source,
            'Technical Overview',
        );

        self::assertStringContainsString('- The product uses a modular architecture.', $result['content']);
        self::assertStringNotContainsString('| Work area |', $result['content']);
    }

    public function test_it_does_not_invent_or_drop_somali_work_details(): void
    {
        $service = app(GaradDocumentCompositionService::class);
        $facts = ['identity' => [], 'meeting' => [], 'other' => []];

        $withoutPolish = $service->compose(
            [...$facts, 'work' => ['waxaan asbucan sameeyey website ka arkaan oo qeebo kala duwan ka kobnaa']],
            'Create a weekly report.',
            'Weekly Report',
        );
        $standalonePolish = $service->compose(
            [...$facts, 'work' => ['waxaan kudaray qurxin']],
            'Create a weekly report.',
            'Weekly Report',
        );

        self::assertStringNotContainsString('qurxin', mb_strtolower($withoutPolish['content']));
        self::assertStringNotContainsString('visual improvements', mb_strtolower($withoutPolish['content']));
        // The English request renders the standalone Somali polish fact in English rather than dropping it.
        self::assertStringContainsString('I also added visual improvements.', $standalonePolish['content']);
    }

    public function test_document_depth_adapts_to_available_verified_facts(): void
    {
        $service = app(GaradDocumentCompositionService::class);
        $short = $service->compose(
            [
                'identity' => [],
                'work' => ['I updated the customer guide.'],
                'meeting' => [],
                'other' => [],
            ],
            'Create a short weekly report.',
            'Weekly Report',
        );
        $detailed = $service->compose(
            [
                'identity' => ['Sam Lee · Operations Lead'],
                'work' => [
                    'Reviewed the customer guide.',
                    'Updated the onboarding checklist.',
                ],
                'meeting' => [
                    'The operations team reviewed the handoff process.',
                    'The service group discussed support coverage.',
                ],
                'other' => ['The report includes an open-questions table supplied by the user.'],
            ],
            'Create a detailed weekly report. Include the stated work and both meetings.',
            'Weekly Report',
        );

        self::assertSame(1, substr_count($short['content'], '## Work & Activity'));
        self::assertStringNotContainsString('## Activity Log', $short['content']);
        self::assertStringNotContainsString('### Meeting', $short['content']);
        self::assertSame(2, preg_match_all('/^### Meeting \d+$/m', $detailed['content']));
        self::assertStringContainsString('Reviewed the customer guide.', $detailed['content']);
        self::assertStringContainsString('Updated the onboarding checklist.', $detailed['content']);
        self::assertStringContainsString('The operations team reviewed the handoff process.', $detailed['content']);
        self::assertStringContainsString('The service group discussed support coverage.', $detailed['content']);
        self::assertStringNotContainsString('page break', mb_strtolower($detailed['content']));
    }

    public function test_a_longer_business_report_composes_all_verified_sections_without_a_title_specific_template(): void
    {
        $source = 'Create a detailed business report. The team reviewed the customer guide, updated the onboarding checklist, '
            . 'and documented the service handoff. The operations group discussed the handoff process, and the support group '
            . 'reviewed coverage. The report also includes the user-provided open-questions list.';
        $result = app(GaradDocumentCompositionService::class)->compose(
            [
                'identity' => [],
                'work' => [
                    'The team reviewed the customer guide.',
                    'The team updated the onboarding checklist.',
                    'The team documented the service handoff.',
                ],
                'meeting' => [
                    'The operations group discussed the handoff process.',
                    'The support group reviewed coverage.',
                ],
                'other' => ['The report includes the user-provided open-questions list.'],
            ],
            $source,
            'Business Report',
        );

        self::assertStringContainsString('## Work & Activity', $result['content']);
        self::assertStringContainsString('## Meetings & Coordination', $result['content']);
        self::assertStringContainsString('## Additional Details', $result['content']);
        self::assertStringNotContainsString('## Activity Log', $result['content']);
        self::assertStringContainsString(
            'The report brings together the reported work activities with meeting discussions',
            $result['content'],
        );
        self::assertSame(2, preg_match_all('/^### Meeting \d+$/m', $result['content']));
        self::assertStringContainsString('The team reviewed the customer guide.', $result['content']);
        self::assertStringContainsString('The team updated the onboarding checklist.', $result['content']);
        self::assertStringContainsString('The team documented the service handoff.', $result['content']);
        self::assertStringContainsString('The operations group discussed the handoff process.', $result['content']);
        self::assertStringContainsString('The support group reviewed coverage.', $result['content']);
        self::assertStringContainsString('user-provided open-questions list', $result['content']);
        self::assertStringNotContainsString('Arkaan', $result['content']);
    }

    public function test_a_requested_page_count_is_preserved_as_a_target_not_used_to_add_filler(): void
    {
        $source = 'Create a professional report designed for exactly 2 pages. I reviewed the customer guide.';
        $this->app->instance('request', Request::create('/', 'POST', ['raw_user_input' => $source]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Professional Report',
            'page_count' => 3,
            'content' => 'A long unsupported report that should not be used.',
            'source_facts' => [
                ['section' => 'work', 'quote' => 'I reviewed the customer guide.'],
            ],
        ]);

        self::assertSame(2, $result['page_count']);
        self::assertSame('## Work & Activity' . "\n\n" . '- I reviewed the customer guide.', $result['content']);
        self::assertStringNotContainsString('unsupported', $result['content']);
    }

    public function test_report_composition_parses_into_renderable_semantic_blocks(): void
    {
        $source = 'Create a weekly report. I updated the client guide. I met with the support group.';
        $this->app->instance('request', Request::create('/', 'POST', ['raw_user_input' => $source]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Weekly Report',
            'content' => 'Ignored model content.',
            'source_facts' => [
                ['section' => 'work', 'quote' => 'I updated the client guide.'],
                ['section' => 'meeting', 'quote' => 'I met with the support group.'],
            ],
        ]);
        $semantic = app(\App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser::class)
            ->parseSemanticBlocks($result['content']);
        $types = array_column($semantic['blocks'], 'type');
        $html = app(\App\Extensions\AIChatPro\System\Services\Document\GaradDocumentHtmlRenderer::class)
            ->render($semantic['blocks'], app(\App\Extensions\AIChatPro\System\Services\Document\GaradDocumentParser::class));

        self::assertContains('heading', $types);
        self::assertGreaterThanOrEqual(2, count(array_filter($types, static fn (string $type): bool => $type === 'heading')));
        self::assertGreaterThanOrEqual(2, count(array_filter($types, static fn (string $type): bool => $type !== 'heading')));
        self::assertStringContainsString('I updated the client guide.', $html);
        self::assertStringContainsString('I met with the support group.', $html);
    }

    public function test_uploaded_document_facts_are_accepted_as_grounding_sources(): void
    {
        $documentChat = new class(new \App\Extensions\AIChatPro\System\Services\DocumentRepository(
            new \App\Extensions\AIChatPro\System\Services\DocumentReaderService,
        )) extends \App\Extensions\AIChatPro\System\Services\DocumentChatService {
            public function buildContextForChat(int $chatId, string $prompt, int $maxChunks = 6): ?string
            {
                return 'Document: service-notes.txt'
                    . "\nThe team reviewed the service handbook and updated the onboarding checklist."
                    . "\nThe operations group discussed the handoff process.";
            }
        };
        $this->app->instance(\App\Extensions\AIChatPro\System\Services\DocumentChatService::class, $documentChat);
        $this->app->instance('request', Request::create('/', 'POST', [
            'chat_id' => 555,
            'raw_user_input' => 'Create a report from the attached service notes.',
        ]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Service Handoff Report',
            'content' => '',
            'source_facts' => [
                ['section' => 'work', 'quote' => 'The team reviewed the service handbook and updated the onboarding checklist.'],
                ['section' => 'meeting', 'quote' => 'The operations group discussed the handoff process.'],
            ],
        ]);

        self::assertSame('user_request_and_uploaded_documents', $result['grounding']['source']);
        self::assertTrue($result['grounding']['uploaded_document_context']);
        self::assertStringContainsString('The team reviewed the service handbook', $result['content']);
        self::assertStringContainsString('The operations group discussed the handoff process.', $result['content']);
    }

    public function test_it_rejects_a_document_with_no_verifiable_facts(): void
    {
        $this->app->instance('request', Request::create('/', 'POST', [
            'raw_user_input' => 'Create a factual report about the project.',
        ]));

        $this->expectException(\RuntimeException::class);
        app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Project report',
            'content' => 'The project is complete.',
            'source_facts' => [
                ['section' => 'work', 'quote' => 'The project is complete.'],
            ],
        ]);
    }

    public function test_pdf_creation_fails_closed_when_the_original_user_request_is_missing(): void
    {
        $this->app->instance('request', Request::create('/', 'POST'));

        $this->expectException(\RuntimeException::class);
        app(GaradArtifactService::class)->createPdf([
            'title' => 'Invented dated report',
            'content' => 'Unsupported document content.',
            'source_facts' => [
                ['section' => 'work', 'quote' => 'Unsupported document content.'],
            ],
        ]);
    }

    public function test_it_keeps_an_exact_user_supplied_title(): void
    {
        $this->app->instance('request', Request::create('/', 'POST', [
            'raw_user_input' => 'Please create my Weekly report. I worked on the website.',
        ]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Weekly report',
            'content' => 'I worked on the website.',
            'source_facts' => [
                ['section' => 'work', 'quote' => 'I worked on the website.'],
            ],
        ]);

        self::assertSame('Weekly report', $result['title']);
    }

    public function test_it_removes_unsupported_date_details_from_the_title(): void
    {
        $this->app->instance('request', Request::create('/', 'POST', [
            'raw_user_input' => 'Create my Arkaan Weekly report. I reviewed the client guide.',
        ]));

        $result = app(GaradDocumentGroundingService::class)->prepare([
            'title' => 'Arkaan Weekly report — Week ending 08 October 2026',
            'content' => 'Create my Arkaan Weekly report.',
            'source_facts' => [
                ['section' => 'other', 'quote' => 'Create my Arkaan Weekly report.'],
                ['section' => 'work', 'quote' => 'I reviewed the client guide.'],
            ],
        ]);

        self::assertSame('Arkaan Weekly report', $result['title']);
    }

    public function test_it_resolves_the_brand_name_from_a_somali_brand_instruction(): void
    {
        $service = app(GaradArtifactService::class);
        $method = new \ReflectionMethod($service, 'requestedBrandName');
        $method->setAccessible(true);

        self::assertSame(
            'Arkaan',
            $method->invoke($service, 'adoo adeegsanaya brand ga Arkaan waxaan asbucan sameeyey website-ka'),
        );
    }
}
