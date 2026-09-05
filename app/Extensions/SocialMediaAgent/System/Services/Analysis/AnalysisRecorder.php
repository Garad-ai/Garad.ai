<?php

namespace App\Extensions\SocialMediaAgent\System\Services\Analysis;

use App\Actions\Notify;
use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaAnalysis;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\OpenAiParamHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AnalysisRecorder
{
    public function recordForAgent(string $type, SocialMediaAgent $agent, string $reportText): SocialMediaAnalysis
    {

        $analysis = SocialMediaAnalysis::create([
            'user_id'    => $agent->user_id,
            'type'       => $type,
            'agent_id'   => $agent->id,
            'summary'    => $this->generateSummary($type, $reportText),
            'report_text'=> $reportText,
            'created_at' => now(),
            'read_at'    => null,
        ]);

        $this->notifyUser($analysis, $agent);

        return $analysis;
    }

    public function generateSummary(string $type, string $reportText): string
    {
        $cleanReport = trim(preg_replace('!\s+!', ' ', strip_tags($reportText)));

        if ($cleanReport === '') {
            return '';
        }

        $prompt = <<<PROMPT
Summarize the following {$type} analysis into two short, action-focused sentences (max 7 total words). Write in English, no bullet points, no titles.

Report:
{$cleanReport}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(20)->post('https://api.openai.com/v1/chat/completions', OpenAiParamHelper::sanitizeChatParams([
                'model'       => config('services.openai.summary_model', EntityEnum::GPT_5_MINI->value),
                'temperature' => 0.3,
                'max_tokens'  => 20,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'You create concise, upbeat two-sentence summaries for social media performance notifications.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]));

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            if ($content !== '') {
                return Str::limit($content, 500);
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to summarize social media analysis', [
                'type'    => $type,
                'message' => $exception->getMessage(),
            ]);
        }

        return Str::limit($cleanReport, 220);
    }

    protected function notifyUser(SocialMediaAnalysis $analysis, SocialMediaAgent $agent): void
    {
        $user = $agent->user;

        if (! $user) {
            return;
        }

        try {
            $title = Str::headline(str_replace('_', ' ', $analysis->type)) . ' ready';
            $message = Str::limit(
                preg_replace('!\s+!', ' ', strip_tags($analysis->report_text)),
                180
            );

            $link = '#';

            if (function_exists('route')) {
                try {
                    $link = route('dashboard.user.social-media.agent.chat.index', $agent->id) . '?analysis=' . $analysis->id;
                } catch (Throwable $routeException) {
                    Log::notice('Unable to build analysis notification link', [
                        'analysis_id' => $analysis->id,
                        'message'     => $routeException->getMessage(),
                    ]);
                }
            }

            Notify::to($user, $title, $message, $link);
        } catch (Throwable $exception) {
            Log::warning('Failed to send social media analysis notification', [
                'analysis_id' => $analysis->id,
                'agent_id'    => $agent->id,
                'message'     => $exception->getMessage(),
            ]);
        }
    }
}
