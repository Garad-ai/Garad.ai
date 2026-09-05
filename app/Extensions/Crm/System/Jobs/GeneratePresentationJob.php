<?php

namespace App\Extensions\Crm\System\Jobs;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\EntityServiceProvider;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmPresentation;
use App\Extensions\Crm\System\Models\CrmPresentationSlide;
use App\Extensions\Crm\System\Models\SalesInvoice;
use App\Extensions\Crm\System\Services\CrmGammaService;
use App\Helpers\Classes\ApiHelper;
use App\Models\Usage;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class GeneratePresentationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $presentationId) {}

    public function handle(): void
    {
        app(EntityServiceProvider::class, ['app' => app()])->boot();

        $presentation = CrmPresentation::find($this->presentationId);

        if (! $presentation || $presentation->status !== 'pending') {
            return;
        }

        try {
            $this->generate($presentation);
        } catch (Exception $e) {
            Log::error('[Crm] GeneratePresentationJob failed', [
                'presentation_id' => $this->presentationId,
                'error'           => $e->getMessage(),
            ]);

            $this->failPresentation($presentation, $e->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $presentation = CrmPresentation::find($this->presentationId);

        if ($presentation && $presentation->status === 'pending') {
            $this->failPresentation($presentation, $exception?->getMessage() ?? 'Generation failed.');
        }
    }

    private function generate(CrmPresentation $presentation): void
    {
        $user = $presentation->user;

        $deal = CrmDeal::where('user_id', $presentation->user_id)
            ->with(['contact', 'company', 'stage', 'tasks'])
            ->find($presentation->crm_deal_id);

        if (! $deal) {
            $this->failPresentation($presentation, __('The linked deal could not be found.'));

            return;
        }

        $invoice = SalesInvoice::where('user_id', $presentation->user_id)
            ->where('crm_deal_id', $deal->id)
            ->with('items')
            ->first();

        if ($presentation->isGamma()) {
            $this->generateWithGamma($presentation, $deal, $invoice);

            return;
        }

        $style = $presentation->style ?? 'corporate';

        $chatDriver = Entity::driver()->forUser($user);

        if (! $chatDriver->hasCreditBalance()) {
            $this->failPresentation($presentation, __('Insufficient credits for text generation.'));

            return;
        }

        $slideData = $this->generateSlideContent($chatDriver, $deal, $invoice, $style);
        $slides = $slideData['slides'] ?? null;

        if (! is_array($slides) || count($slides) === 0) {
            $this->failPresentation($presentation, __('Failed to generate slide content.'));

            return;
        }

        $responseText = json_encode($slideData, JSON_UNESCAPED_UNICODE);
        $chatDriver->input($responseText)->calculateCredit()->decreaseCredit();

        $slideCount = count($slides);

        $imageDriver = Entity::driver(EntityEnum::NANO_BANANA_PRO)
            ->forUser($user)
            ->inputImageCount($slideCount)
            ->calculateCredit();

        if (! $imageDriver->hasCreditBalance()) {
            $this->failPresentation($presentation, __('Insufficient image credits for :count slides.', ['count' => $slideCount]));

            return;
        }

        $presentation->update([
            'status'       => 'generating',
            'total_slides' => $slideCount,
        ]);

        $submittedCount = 0;
        $lastError = null;

        foreach ($slides as $index => $slide) {
            $requestId = null;

            try {
                $requestId = FalAIService::generate(
                    $slide['image_prompt'] ?? $slide['title'],
                    EntityEnum::NANO_BANANA_PRO,
                    ['aspect_ratio' => '16:9']
                );
            } catch (Exception $e) {
                $lastError = $e->getMessage();

                Log::warning('[Crm] Slide image submission failed', [
                    'presentation_id' => $presentation->id,
                    'slide_index'     => $index,
                    'error'           => $e->getMessage(),
                ]);
            }

            if ($requestId) {
                $submittedCount++;
            }

            CrmPresentationSlide::create([
                'crm_presentation_id' => $presentation->id,
                'sort_order'          => $index,
                'slide_type'          => $slide['type'] ?? 'cover',
                'title'               => $slide['title'] ?? __('Slide :num', ['num' => $index + 1]),
                'content'             => $slide['content'] ?? null,
                'image_prompt'        => $slide['image_prompt'] ?? null,
                'fal_request_id'      => $requestId,
                'status'              => $requestId ? 'generating' : 'failed',
                'error_message'       => $requestId ? null : __('Failed to submit image generation request.'),
            ]);
        }

        if ($submittedCount === 0) {
            $this->failPresentation($presentation, $lastError ?? __('Image generation failed for all slides.'));

            return;
        }

        Usage::getSingle()->updateImageCounts($imageDriver->calculate());
        $imageDriver->decreaseCredit();
    }

    private function generateWithGamma(CrmPresentation $presentation, CrmDeal $deal, ?SalesInvoice $invoice): void
    {
        if (! CrmGammaService::isConfigured()) {
            $this->failPresentation($presentation, __('Gamma API key is not configured.'));

            return;
        }

        $numCards = (int) data_get($presentation->metadata, 'num_cards', 8);

        $generationId = (new CrmGammaService)->generate($deal, $invoice, $numCards);

        $presentation->update([
            'status'        => 'generating',
            'generation_id' => $generationId,
            'total_slides'  => $numCards,
        ]);
    }

    private function failPresentation(CrmPresentation $presentation, string $message): void
    {
        $metadata = $presentation->metadata ?? [];
        $metadata['error'] = Str::limit($message, 500);

        $presentation->update([
            'status'   => 'failed',
            'metadata' => $metadata,
        ]);
    }

    private function generateSlideContent($chatDriver, CrmDeal $deal, ?SalesInvoice $invoice, string $style): array
    {
        $context = [
            'deal' => [
                'title'               => $deal->title,
                'value'               => $deal->value,
                'currency'            => $deal->currency ?? 'USD',
                'description'         => $deal->description,
                'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                'stage'               => $deal->stage?->name,
            ],
            'company' => $deal->company ? [
                'name'     => $deal->company->name,
                'industry' => $deal->company->industry,
                'website'  => $deal->company->website,
                'address'  => $deal->company->address,
                'city'     => $deal->company->city,
                'country'  => $deal->company->country,
            ] : null,
            'contact' => $deal->contact ? [
                'name'      => $deal->contact->full_name,
                'job_title' => $deal->contact->job_title,
                'email'     => $deal->contact->email,
                'phone'     => $deal->contact->phone,
            ] : null,
            'invoice' => $invoice ? [
                'invoice_number' => $invoice->invoice_number,
                'total'          => (float) $invoice->total,
                'currency'       => $invoice->currency,
                'items'          => $invoice->items->map(fn ($item) => [
                    'description' => $item->description,
                    'quantity'    => (float) $item->quantity,
                    'unit_price'  => (float) $item->unit_price,
                    'total'       => (float) $item->total,
                ])->toArray(),
            ] : null,
        ];

        $systemPrompt = $this->buildSlideSystemPrompt($style);
        $userMessage = json_encode($context, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

        $apiKey = ApiHelper::setOpenAiKey();
        config(['openai.api_key' => $apiKey]);

        $response = OpenAI::chat()->create([
            'model'           => $chatDriver->enum()->value,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        $content = $response->choices[0]->message->content;

        return json_decode($content, true) ?? [];
    }

    private function buildSlideSystemPrompt(string $style): string
    {
        return <<<PROMPT
You are a pitch deck content specialist. Generate a professional {$style}-style pitch deck with 5-8 slides based on the deal information provided.

Return a JSON object with this exact structure:
{
  "slides": [
    {
      "type": "cover",
      "title": "Slide title text",
      "content": {
        "subtitle": "Optional subtitle",
        "tagline": "Optional tagline",
        "bullet_points": ["Point 1", "Point 2"],
        "description": "Optional longer description"
      },
      "image_prompt": "A detailed image generation prompt..."
    }
  ]
}

SLIDE TYPES (use these in order, include 5-8 based on available data):
1. "cover" - Title slide with company name, deal title, date (ALWAYS include)
2. "company_overview" - About the company, industry context (include if company data available)
3. "problem" - The challenge/opportunity this deal addresses (include if description available)
4. "solution" - What's being offered, services/products (ALWAYS include)
5. "pricing" - Deal value, line items from invoice if available (ALWAYS include)
6. "timeline" - Expected milestones, close date (include if close date available)
7. "team" - Key contact information (include if contact data available)
8. "cta" - Thank you / call-to-action slide (ALWAYS include)

IMAGE PROMPT GUIDELINES:
- Each image_prompt should describe a professional 16:9 presentation slide background/visual
- Describe the visual style: colors, gradients, shapes, illustrations, icons
- Keep the "{$style}" aesthetic consistent across all slides of THIS deck, but derive a distinctive palette for this specific deck from the company name, industry, and deal topic - do NOT reuse the same colors for every deck
- Focus on VISUAL ELEMENTS only - geometric shapes, abstract backgrounds, relevant illustrations
- DO NOT include text in the image prompt - the text content is in the "content" field
- Be highly specific about colors, composition, and mood (60-100 words per prompt)
- Use professional business/corporate imagery themes
- For "corporate": a dark professional base (choose one that suits the industry: deep navy, charcoal, slate, deep teal, forest green, or aubergine) paired with EXACTLY ONE metallic or bright accent (choose one that fits the brand/industry: gold, silver, bronze, copper, electric blue, or emerald), with clean geometric patterns. Vary the base+accent pairing per deck so different companies get visually different decks.
- For "modern": vibrant gradients, bold shapes, dynamic compositions; pick a gradient hue family that reflects the company's industry
- For "minimal": white space, subtle gray tones, thin lines, elegant; introduce a single restrained accent hue drawn from the industry
- For "creative": colorful illustrations, unique layouts, artistic elements themed around the company's field

IMPORTANT: The "content" field contains ALL the text that will appear on the slide. The "image_prompt" is ONLY for the visual background/design of the slide.
PROMPT;
    }
}
