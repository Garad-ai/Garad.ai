<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Extensions\AIChatProDeepResearch\System\Http\Controllers\DeepResearchController;
use Tests\TestCase;

final class DeepResearchErrorClassificationTest extends TestCase
{
    public function test_openai_access_denial_is_classified_as_provider_access(): void
    {
        [$code, $message] = DeepResearchController::classifyProviderError(
            'Project does not have access to model `o3-deep-research` (HTTP 403)'
        );

        $this->assertSame('provider_access_denied', $code);
        $this->assertStringNotContainsStringIgnoringCase('credits left', (string) $message);
    }

    public function test_gemini_zero_quota_is_classified_as_provider_quota(): void
    {
        [$code] = DeepResearchController::classifyProviderError(
            'HTTP 429 RESOURCE_EXHAUSTED: You exceeded your current quota, free tier limit: 0'
        );

        $this->assertSame('provider_quota', $code);
    }

    public function test_unknown_errors_have_no_category_message(): void
    {
        [$code, $message] = DeepResearchController::classifyProviderError('cURL error 28: timeout');

        $this->assertSame('provider_error', $code);
        $this->assertNull($message);
    }
}
