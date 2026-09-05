<?php

namespace App\Extensions\AdvancedImage\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AdvancedImage\System\Services\Traits\HasDownloadImage;
use App\Extensions\AdvancedImage\System\Services\Traits\HasFalAI;
use App\Extensions\AdvancedImage\System\Services\Traits\UseImage;
use App\Helpers\Classes\ApiHelper;
use App\Models\UserOpenai;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class FalAIService
{
    use HasDownloadImage;
    use HasFalAI;
    use UseImage;

    public string $tool;

    public Request $request;

    public function generate(): ?array
    {
        return match ($this->tool) {
            'reimagine'                    => $this->reimagine(),
            'remove_background'            => $this->remove_background(),
            'inpainting'                   => $this->inpainting(),
            'remove_text'                  => $this->remove_text(),
            'sketch_to_image'              => $this->sketch_to_image(),
            'replace_background'           => $this->replace_background(),
            'cleanup'                      => $this->cleanup(),
            'style_transfer'               => $this->style_transfer(),
            'image_relight'                => $this->image_relight(),
            default                        => [
                'status'  => 'error',
                'message' => 'Unable to process action.',
            ]
        };
    }

    /**
     images: [
               'https://betamagicai.liquid-themes.com/uploads/ordek.jpg',
     ]
     */
    public function sketch_to_image(): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Turn this sketch into a photorealistic image. Prompt: ' . $this->request->get('description'),
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('sketch_file'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function remove_background(): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Remove the background, keeping only the main subject. Transparent.',
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function cleanup(): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Clean up the masked area to remove imperfections or unwanted elements. Preserve the other areas.',
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                    $this->request->file('mask_file'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function remove_text(): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Remove all visible text while preserving the image content.',
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function inpainting(): array
    {
        try {
            $response = self::generateImage(
                prompt: $this->request->get('description') . ' Blend naturally with the rest of the image.',
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function replace_background(): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Replace the background with: ' . $this->request->get('description'),
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function reimagine(): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Reimagine this image as: ' . $this->request->get('description'),
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function style_transfer(): array
    {
        $this->request->validate([
            'uploaded_image'   => 'required|image',
            'reference_image'  => 'required|image',
        ]);

        try {
            $response = self::generateImage(
                prompt: 'Apply the general visual style of: [image 1] to [image 2].' . $this->request->get('description'),
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                    $this->request->file('reference_image'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function image_relight(): array
    {
        $this->request->validate([
            'uploaded_image' => 'required|image',
            'image_relight'  => 'required|image',
        ]);

        try {
            $response = self::generateImage(
                prompt: 'Adjust image lighting to: [image 1] to [image 2]. Prompt: ' . $this->request->get('description') . ' style: ' . $this->request->get('style'),
                entity: $this->resolveEntity(),
                images: self::createImageUrls([
                    $this->request->file('uploaded_image'),
                    $this->request->file('image_relight'),
                ])
            );

            return $this->responseArray($response);

        } catch (Exception $exception) {
            return [
                'status'  => 'error',
                'message' => $action['message'] ?? 'Unable to process action.',
            ];
        }
    }

    public function responseArray($response): array
    {
        // Grok sync mode returns an array with images directly
        if (is_array($response)) {
            $imageUrl = data_get($response, 'images.0.url', '');
            $path = $imageUrl ? $this->downloadAndSaveImageFromUrl($imageUrl) : '';

            return [
                'task_id'     => null,
                'photo'       => $path,
                'status'      => 'success',
                'task_status' => 'COMPLETED',
                'message'     => 'Image generated successfully.',
                'link'        => '',
            ];
        }

        return [
            'task_id'     => $response->json('request_id'),
            'photo'       => '',
            'status'      => 'success',
            'task_status' => 'IN_PROGRESS',
            'message'     => 'Task is processing.',
            'link'        => $response->json('response_url'),
        ];
    }

    public function checkStatus(UserOpenai $task): UserOpenai
    {
        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->get($task['payload']['link']);

        if ($response->ok() && $images = $response->json('images')) {
            $image = Arr::first($images);

            $path = $this->downloadAndSaveImageFromUrl($image['url']);

            $task->update([
                'title'   => $this->title($path),
                'status'  => 'COMPLETED',
                'output'  => $path,
            ]);
        }

        return $task;
    }

    public function setTool(string $tool): self
    {
        $this->tool = $tool;

        return $this;
    }

    public function setRequest(Request $request): FalAIService
    {
        $this->request = $request;

        return $this;
    }

    private function resolveEntity(): EntityEnum
    {
        $model = $this->request->get('ai_model') ?? EntityEnum::FLUX_PRO_KONTEXT->value;

        if ($model === EntityEnum::FLUX_PRO_KONTEXT->value && in_array($this->tool, ['cleanup', 'image_relight', 'style_transfer'], true)) {
            return EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI;
        }

        try {
            return EntityEnum::fromSlug($model);
        } catch (Exception) {
            return EntityEnum::FLUX_PRO_KONTEXT;
        }
    }
}
