<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Services;

use App\Extensions\AiChatProSmartImage\System\Services\SmartImageService;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\AIController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Throwable;

class AiChatProService
{
    private static function availableTools(): array
    {
        $tools = [];

        if ((bool) setting('ai_chat_pro_image_generation_feature', '0')) {
            $tools[] = [
                'type'        => 'function',
                'name'        => 'generate_image',
                'description' => 'Generate a brand new AI image. Use when the user asks to create, generate, draw, design, render, or make an image. Do not use search_images for creation requests.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'prompt' => [
                            'type'        => 'string',
                            'description' => 'A detailed image-generation prompt that faithfully preserves the user request.',
                        ],
                    ],
                    'required' => ['prompt'],
                    'additionalProperties' => false,
                ],
            ];
        }

        if (MarketplaceHelper::isRegistered('ai-chat-pro-smart-image') && SmartImageService::isEnabled()) {
            $tools[] = SmartImageService::toolDefinition();
        }

        if (Auth::check()) {
            $tools = array_merge($tools, self::artifactTools());
        }

        if (Auth::check() && MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
            $tools = array_merge($tools, self::imageTools());
        }

        if (Auth::check() && MarketplaceHelper::isRegistered('ai-presentation')) {
            $tools = array_merge($tools, self::presentationTools());
        }

        if (Auth::check() && MarketplaceHelper::isRegistered('ai-agent')) {
            $tools = array_merge($tools, self::agentTools());
        }

        if (Auth::check() && MarketplaceHelper::isRegistered('ai-video-pro')) {
            $tools = array_merge($tools, self::videoTools());
        }

        return $tools;
    }

    public static function tools(): array
    {
        return self::availableTools();
    }

    public static function callFunction(?string $functionName, ?string $argsString): ?string
    {
        try {
            return match ($functionName) {
                'generate_image'      => self::generateImage($argsString),
                'search_images'       => self::searchImages($argsString),
                'edit_image'          => app(GaradImageToolService::class)->edit(self::decodeArguments($argsString)),
                'image_status'        => app(GaradImageToolService::class)->status(self::decodeArguments($argsString)),
                'create_pdf'          => app(GaradArtifactService::class)->createPdf(self::decodeArguments($argsString)),
                'create_docx'         => app(GaradArtifactService::class)->createDocx(self::decodeArguments($argsString)),
                'create_xlsx'         => app(GaradArtifactService::class)->createXlsx(self::decodeArguments($argsString)),
                'create_presentation' => app(GaradPresentationToolService::class)->create(self::decodeArguments($argsString)),
                'presentation_status' => app(GaradPresentationToolService::class)->status(self::decodeArguments($argsString)),
                'list_agents'         => app(GaradAgentToolService::class)->list(),
                'run_agent'           => app(GaradAgentToolService::class)->run(self::decodeArguments($argsString)),
                'generate_video'      => app(GaradVideoToolService::class)->create(self::decodeArguments($argsString)),
                'video_status'        => app(GaradVideoToolService::class)->status(self::decodeArguments($argsString)),
                default               => null,
            };
        } catch (RuntimeException $e) {
            Log::warning('Chat Pro tool rejected', [
                'function' => $functionName,
                'error'    => $e->getMessage(),
                'user_id'  => Auth::id(),
            ]);

            return '⚠️ ' . e($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Chat Pro tool failed', [
                'function' => $functionName,
                'error'    => $e->getMessage(),
                'user_id'  => Auth::id(),
            ]);

            return '❌ ' . e(__('The requested Chat Pro tool could not be completed. Please try again.'));
        }
    }

    private static function artifactTools(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'create_pdf',
                'description' => 'Create a downloadable PDF document from finalized content. Use when the user explicitly asks for a PDF file, report, proposal, letter, document, or export as PDF.',
                'parameters'  => self::textDocumentParameters(),
            ],
            [
                'type'        => 'function',
                'name'        => 'create_docx',
                'description' => 'Create a downloadable Microsoft Word DOCX document from finalized content. Use when the user asks for a Word file or editable document.',
                'parameters'  => self::textDocumentParameters(),
            ],
            [
                'type'        => 'function',
                'name'        => 'create_xlsx',
                'description' => 'Create a downloadable Microsoft Excel XLSX spreadsheet. Use for tables, budgets, trackers, structured datasets, schedules, calculations, or when the user asks for Excel.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'title' => [
                            'type'        => 'string',
                            'description' => 'Spreadsheet title and filename.',
                        ],
                        'headers' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Column headers in display order.',
                        ],
                        'rows' => [
                            'type'        => 'array',
                            'description' => 'Rows of spreadsheet data. Each inner array is one row and should align with headers.',
                            'items'       => [
                                'type'  => 'array',
                                'items' => new \stdClass,
                            ],
                        ],
                    ],
                    'required' => ['title', 'rows'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private static function imageTools(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'edit_image',
                'description' => 'Edit the most recently attached image in this Chat Pro conversation. Use when the user asks to change, remove, replace, restyle, retouch, transform, or otherwise modify an uploaded image.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'prompt' => [
                            'type'        => 'string',
                            'description' => 'Precise edit instruction describing what must change and what should remain unchanged.',
                        ],
                    ],
                    'required' => ['prompt'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'image_status',
                'description' => 'Check an image edit/generation job created from Chat Pro and return the result when it is complete.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'record_id' => ['type' => 'integer', 'description' => 'Image Job ID returned by edit_image.'],
                    ],
                    'required' => ['record_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private static function presentationTools(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'create_presentation',
                'description' => 'Create a real downloadable AI presentation using Garad AI Presentation. Use when the user asks for slides, PowerPoint, PPTX, a deck, or presentation. Do not merely write a slide outline when a presentation file is requested.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'topic' => ['type' => 'string', 'description' => 'Complete presentation brief/topic including important content requirements.'],
                        'slide_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'description' => 'Number of slides/cards.'],
                        'language' => ['type' => 'string', 'description' => 'Language code or short language value, e.g. en, so, ar.'],
                        'text_length' => ['type' => 'string', 'enum' => ['Brief', 'Medium', 'Detailed', 'Extensive']],
                        'export_as' => ['type' => 'string', 'enum' => ['pptx', 'pdf']],
                        'tone' => ['type' => 'string'],
                        'audience' => ['type' => 'string'],
                        'image_source' => [
                            'type' => 'string',
                            'enum' => ['aiGenerated', 'pictographic', 'unsplash', 'giphy', 'webAllImages', 'webFreeToUse', 'webFreeToUseCommercially', 'placeholder', 'noImages'],
                        ],
                    ],
                    'required' => ['topic'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'presentation_status',
                'description' => 'Check a Garad AI presentation generation job and return its download/open link when complete.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'generation_id' => ['type' => 'string', 'description' => 'Generation ID returned by create_presentation.'],
                    ],
                    'required' => ['generation_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private static function videoTools(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'generate_video',
                'description' => 'Generate a real AI video using Garad AI Video Pro. Use when the user asks to create, generate, render, or make a video from a text prompt.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'prompt' => ['type' => 'string', 'description' => 'Detailed video generation prompt.'],
                        'quality' => ['type' => 'string', 'enum' => ['fast', 'quality'], 'description' => 'fast is cheaper/faster; quality prioritizes output quality.'],
                        'duration' => ['type' => 'integer', 'enum' => [4, 6, 8], 'description' => 'Video duration in seconds.'],
                        'resolution' => ['type' => 'string', 'enum' => ['720p', '1080p']],
                        'aspect_ratio' => ['type' => 'string', 'enum' => ['16:9', '9:16', '1:1']],
                        'generate_audio' => ['type' => 'boolean', 'description' => 'Whether the generated video should include generated audio when supported.'],
                    ],
                    'required' => ['prompt'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'video_status',
                'description' => 'Check an AI Video Pro generation job created from Chat Pro and return the video link when complete.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'video_id' => ['type' => 'integer', 'description' => 'Video ID returned by generate_video.'],
                    ],
                    'required' => ['video_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private static function agentTools(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'list_agents',
                'description' => 'List the signed-in user’s existing Garad AI agents/workflows. Use when the user asks what agents they have or when you need to identify the correct agent before running it.',
                'parameters'  => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                    'additionalProperties' => false,
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'run_agent',
                'description' => 'Run one of the signed-in user’s existing Garad AI agents/workflows for a specific task. The agent must be selected by name or ID and is always ownership-scoped to the current user.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'agent' => ['type' => 'string', 'description' => 'Agent name or numeric ID.'],
                        'task' => ['type' => 'string', 'description' => 'The concrete task/instructions to execute.'],
                    ],
                    'required' => ['agent', 'task'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private static function textDocumentParameters(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title' => [
                    'type'        => 'string',
                    'description' => 'Document title and filename.',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Complete finalized document content. Include headings and bullets where useful.',
                ],
            ],
            'required' => ['title', 'content'],
            'additionalProperties' => false,
        ];
    }

    private static function decodeArguments(?string $argsString): array
    {
        if (blank($argsString)) {
            return [];
        }

        $decoded = json_decode($argsString, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private static function searchImages(?string $argsString): string
    {
        if (! MarketplaceHelper::isRegistered('ai-chat-pro-smart-image')) {
            return '';
        }

        $args = self::decodeArguments($argsString);
        $query = $args['query'] ?? '';
        if (empty($query)) {
            return '';
        }

        return SmartImageService::searchAndFormat($query, SmartImageService::getMaxImages());
    }

    public static function generateImage(?string $argsString): string
    {
        if (! (bool) setting('ai_chat_pro_image_generation_feature', '0')) {
            throw new RuntimeException('Image generation is currently disabled for Chat Pro.');
        }

        $args = self::decodeArguments($argsString);
        if (! isset($args['prompt']) || trim((string) $args['prompt']) === '') {
            throw new JsonException('Invalid arguments: prompt is required');
        }

        return app(AIController::class)->generateImageWithOpenAI((string) $args['prompt']);
    }
}
