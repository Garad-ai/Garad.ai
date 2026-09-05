<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\GetCrmAnalyticsAction;

/*
 * Dynamically choose the parent class so this enhancement chains on top of any
 * previously-installed AI Agent sub-extension report enhancement rather than
 * replacing it.
 *
 * Resolution order (whichever is installed, most-specific first):
 *   ChatbotEnhanced → MarketingEnhanced → SocialMediaEnhanced → GenerateReportAction
 *
 * Each level calls parent::execute() then appends its own section.
 */
if (! class_exists(__NAMESPACE__ . '\CrmReportBase')) {
    class_alias(
        class_exists(\App\Extensions\AIAgentToolChatbot\System\Actions\EnhancedGenerateReportAction::class)
            ? \App\Extensions\AIAgentToolChatbot\System\Actions\EnhancedGenerateReportAction::class
            : (class_exists(\App\Extensions\AIAgentToolMarketingBot\System\Actions\EnhancedGenerateReportAction::class)
                ? \App\Extensions\AIAgentToolMarketingBot\System\Actions\EnhancedGenerateReportAction::class
                : (class_exists(\App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\EnhancedGenerateReportAction::class)
                    ? \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\EnhancedGenerateReportAction::class
                    : \App\Extensions\AIAgent\System\Actions\GenerateReportAction::class)),
        __NAMESPACE__ . '\CrmReportBase',
    );
}

/**
 * Extends the base report to append structured CRM pipeline analytics when the
 * "crm" section is requested.
 */
class EnhancedGenerateReportAction extends CrmReportBase
{
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $context = parent::execute($config, $context, $workflow, $run);

        $sections = $config['sections'] ?? ['messages', 'workflows'];
        $sections = is_array($sections)
            ? $sections
            : array_filter(array_map('trim', explode(',', (string) $sections)));

        if (! in_array('crm', $sections, true)) {
            return $context;
        }

        $context['crm_analytics'] = GetCrmAnalyticsAction::buildAnalytics((int) $workflow->user_id);

        return $context;
    }
}
