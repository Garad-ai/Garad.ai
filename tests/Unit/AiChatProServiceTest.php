<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatPro\System\Services\AiChatProService;
use PHPUnit\Framework\TestCase;

final class AiChatProServiceTest extends TestCase
{
    public function test_successful_agent_tool_results_are_internal_but_failures_remain_visible(): void
    {
        self::assertFalse(AiChatProService::shouldExposeToolResult('run_agent', 'completed'));
        self::assertFalse(AiChatProService::shouldExposeToolResult('run_agent', 'working'));
        self::assertTrue(AiChatProService::shouldExposeToolResult('run_agent', 'failed'));
        self::assertTrue(AiChatProService::shouldExposeToolResult('create_pdf', 'completed'));
    }

    public function test_agent_approval_waits_are_visible_and_carry_an_approval_id_only_in_agent_mode(): void
    {
        self::assertSame(
            ['state' => 'waiting_for_approval', 'approvalId' => '42'],
            AiChatProService::toolExecutionPayload('run_agent', 'Paused for approval.', true, '42'),
        );
        self::assertSame(
            ['state' => 'completed'],
            AiChatProService::toolExecutionPayload('run_agent', 'Paused for approval.', false, '42'),
        );
        self::assertTrue(AiChatProService::shouldExposeToolResult('run_agent', 'waiting_for_approval'));
        self::assertSame(
            'waiting_for_approval',
            AiChatProService::progressPayload('run_agent', 'waiting_for_approval')['state'],
        );
    }

    public function test_a_requested_pdf_cannot_create_an_unrequested_spreadsheet_or_other_artifact(): void
    {
        $tools = [
            ['type' => 'function', 'name' => 'create_pdf'],
            ['type' => 'function', 'name' => 'create_docx'],
            ['type' => 'function', 'name' => 'create_xlsx'],
            ['type' => 'function', 'name' => 'create_presentation'],
            ['type' => 'function', 'name' => 'generate_image'],
            ['type' => 'function', 'name' => 'generate_video'],
            ['type' => 'function', 'name' => 'create_scheduled_task'],
        ];

        self::assertSame(
            ['create_pdf'],
            array_column(AiChatProService::filterArtifactCreationTools($tools, 'create_pdf'), 'name')
        );
    }

    public function test_an_analysis_request_has_no_artifact_creation_tools(): void
    {
        $tools = [
            ['type' => 'function', 'name' => 'create_pdf'],
            ['type' => 'function', 'name' => 'create_xlsx'],
            ['type' => 'function', 'name' => 'search_web'],
        ];

        self::assertSame(
            ['search_web'],
            array_column(AiChatProService::filterArtifactCreationTools($tools, null), 'name')
        );
    }

    public function test_a_pdf_request_selects_pdf_before_later_prohibited_formats(): void
    {
        $prompt = 'Create exactly ONE real PDF file. Do not create a DOCX, XLSX, presentation, image, or any other file.';

        self::assertSame('create_pdf', AiChatProService::preferredArtifactToolFromPrompt($prompt));
    }

    public function test_a_negated_output_type_does_not_become_the_selected_artifact(): void
    {
        $prompt = 'Do not create a DOCX or XLSX. Create exactly one PDF.';

        self::assertSame('create_pdf', AiChatProService::preferredArtifactToolFromPrompt($prompt));
    }

    public function test_inspecting_an_attached_image_does_not_trigger_image_generation(): void
    {
        $prompt = 'Device B attachment check: inspect the Arkaan logo image attached to my original message in this conversation. Report only visible text and colors; do not infer anything if the image is unavailable. Do not create files, reminders, or artifacts.';

        self::assertNull(AiChatProService::preferredArtifactToolFromPrompt($prompt));
    }

    public function test_a_pdf_request_does_not_expose_unrequested_file_or_scheduled_task_tools(): void
    {
        $tools = [
            ['type' => 'function', 'name' => 'create_pdf'],
            ['type' => 'function', 'name' => 'create_docx'],
            ['type' => 'function', 'name' => 'create_xlsx'],
            ['type' => 'function', 'name' => 'create_presentation'],
            ['type' => 'function', 'name' => 'generate_image'],
            ['type' => 'function', 'name' => 'generate_video'],
            ['type' => 'function', 'name' => 'create_scheduled_task'],
            ['type' => 'function', 'name' => 'list_scheduled_tasks'],
        ];

        self::assertSame(
            ['create_pdf', 'list_scheduled_tasks'],
            array_column(AiChatProService::filterArtifactCreationTools($tools, 'create_pdf'), 'name'),
        );
    }

    public function test_a_negated_reminder_request_does_not_select_scheduled_task(): void
    {
        self::assertSame(
            'create_pdf',
            AiChatProService::preferredArtifactToolFromPrompt(
                'Create exactly one PDF report. Do not create a reminder or scheduled task.',
            ),
        );
    }

    public function test_explicit_image_creation_still_selects_the_image_tool(): void
    {
        self::assertSame(
            'generate_image',
            AiChatProService::preferredArtifactToolFromPrompt('Create a new logo image for the Arkaan launch.'),
        );
    }

    public function test_a_pdf_request_with_an_attached_logo_still_selects_pdf(): void
    {
        self::assertSame(
            'create_pdf',
            AiChatProService::preferredArtifactToolFromPrompt('Create my weekly report as a PDF using the attached Arkaan logo.'),
        );
    }

    public function test_somali_word_to_pdf_conversion_selects_the_requested_pdf_output(): void
    {
        self::assertSame(
            'create_pdf',
            AiChatProService::preferredArtifactToolFromPrompt(
                'word kaan pdf iiga soo dhig sidookale adoo adeegsanaya logo da iyo brand gaas',
            ),
        );
    }

    public function test_document_creation_tools_expose_grounded_composition_and_page_count_target(): void
    {
        $method = new \ReflectionMethod(AiChatProService::class, 'textArtifactTool');
        $method->setAccessible(true);
        $tool = $method->invoke(
            null,
            'create_pdf',
            'Create a document.',
            'PDF',
        );
        $parameters = $tool['parameters'];

        self::assertContains('page_count', array_keys($parameters['properties']));
        self::assertSame(1, $parameters['properties']['page_count']['minimum']);
        self::assertSame(20, $parameters['properties']['page_count']['maximum']);
        self::assertNotContains('page_count', $parameters['required']);
        self::assertFalse($parameters['additionalProperties']);
    }

    public function test_a_weekly_report_request_is_not_misclassified_as_a_recurring_reminder(): void
    {
        self::assertSame(
            'create_pdf',
            AiChatProService::preferredArtifactToolFromPrompt('Create my weekly work report as a PDF using the attached Arkaan logo.'),
        );
    }

    public function test_a_pdf_request_with_explicitly_prohibited_reminders_selects_pdf(): void
    {
        $prompt = 'Using only the facts and the Arkaan logo from my original message in this conversation, redo my Arkaan weekly work report and create exactly ONE PDF file. Do not create any other file or artifact type, and do not create a reminder, scheduled item, task, or event. Use only these facts: Jabir Osman Gedi is ICT Coordinator; this week I worked on several sections of the Arkaan website and added visual polish; I participated in three meetings: the Arkaan weekly main meeting with multiple agenda items and reporting; a meeting with Baxnaano about a proposed system for tracking work progress; and a meeting discussing the Garad AI launch event.';

        self::assertSame('create_pdf', AiChatProService::preferredArtifactToolFromPrompt($prompt));
    }

    public function test_an_explicit_reminder_request_still_selects_scheduled_task(): void
    {
        self::assertSame(
            'create_scheduled_task',
            AiChatProService::preferredArtifactToolFromPrompt('Remind me tomorrow to send the weekly report.'),
        );
    }

    public function test_current_job_and_scholarship_search_requests_enable_web_search_intent(): void
    {
        self::assertTrue(AiChatProService::requiresWebSearchForPrompt('Find current software engineering jobs.'));
        self::assertTrue(AiChatProService::requiresWebSearchForPrompt('Research a scholarship and explain eligibility.'));
        self::assertTrue(AiChatProService::requiresWebSearchForPrompt('Ii raadi fursado shaqo oo hadda furan.'));
    }

    public function test_explanations_and_negated_searches_do_not_enable_web_search_intent(): void
    {
        self::assertFalse(AiChatProService::requiresWebSearchForPrompt('Explain what a scholarship is.'));
        self::assertFalse(AiChatProService::requiresWebSearchForPrompt('Do not search for jobs; explain how to improve my CV.'));
        self::assertFalse(AiChatProService::requiresWebSearchForPrompt('Find current software engineering jobs without web search.'));
        self::assertFalse(AiChatProService::requiresWebSearchForPrompt('Do not use web search for this job question.'));
    }

    public function test_search_intent_survives_instructions_not_to_include_unverified_results(): void
    {
        self::assertTrue(AiChatProService::requiresWebSearchForPrompt(
            'Search for current software engineering jobs, but do not include any listings that cannot be verified.',
        ));
        self::assertTrue(AiChatProService::requiresWebSearchForPrompt(
            'Find current jobs. If none can be verified, say so without inventing listings.',
        ));
        self::assertTrue(AiChatProService::requiresWebSearchForPrompt(
            'Search for current jobs, but do not use stale search snippets as proof.',
        ));
    }
}
