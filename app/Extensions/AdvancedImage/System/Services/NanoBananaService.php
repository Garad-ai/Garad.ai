<?php

namespace App\Extensions\AdvancedImage\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AdvancedImage\System\Services\Traits\HasDownloadImage;
use App\Extensions\AdvancedImage\System\Services\Traits\HasNanoBanana;
use App\Extensions\AdvancedImage\System\Services\Traits\UseImage;
use App\Helpers\Classes\ApiHelper;
use App\Models\UserOpenai;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class NanoBananaService
{
    use HasDownloadImage;
    use HasNanoBanana;
    use UseImage;

    public string $tool;

    public Request $request;

    public function generate($isPro = false, $isNanoBanana2 = false): ?array
    {
        $entry = EntityEnum::NANO_BANANA_EDIT;

        if ($isNanoBanana2) {
            $entry = EntityEnum::NANO_BANANA_2_EDIT;
        } elseif ($isPro) {
            $entry = EntityEnum::NANO_BANANA_PRO_EDIT;
        }

        return match ($this->tool) {
            'reimagine'                    => $this->reimagine($entry),
            'remove_background'            => $this->remove_background($entry),
            'inpainting'                   => $this->inpainting($entry),
            'remove_text'                  => $this->remove_text($entry),
            'sketch_to_image'              => $this->sketch_to_image($entry),
            'replace_background'           => $this->replace_background($entry),
            'cleanup'                      => $this->cleanup($entry),
            'style_transfer'               => $this->style_transfer($entry),
            'image_relight'                => $this->image_relight($entry),
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
    public function sketch_to_image(EntityEnum $entity): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Turn this sketch into a photorealistic image. Prompt: ' . $this->request->get('description'),
                entity: $entity,
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

    public function remove_background(EntityEnum $entity): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Remove the background, keeping only the main subject.',
                entity: $entity,
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

    public function cleanup(EntityEnum $entity): array
    {
        $this->request->validate([
            'description' => 'required',
        ]);

        try {
            $response = self::generateImage(
                prompt: $this->request->get('description'),
                entity: $entity,
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

    public function remove_text(EntityEnum $entity): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Remove all visible text while preserving the image content.',
                entity: $entity,
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

    public function inpainting(EntityEnum $entity): array
    {
        try {
            $response = self::generateImage(
                prompt: $this->request->get('description') . ' Blend naturally with the rest of the image.',
                entity: $entity,
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

    public function replace_background(EntityEnum $entity): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Replace the background with: ' . $this->request->get('description'),
                entity: $entity,
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

    public function reimagine(EntityEnum $entity): array
    {
        try {
            $response = self::generateImage(
                prompt: 'Reimagine this image as: ' . $this->request->get('description'),
                entity: $entity,
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

    public function style_transfer(EntityEnum $entity): array
    {
        $this->request->validate([
            'uploaded_image'   => 'required|image',
            'reference_image'  => 'required|image',
        ]);

        try {
            $response = self::generateImage(
                prompt: 'Apply the general visual style of: [image 1] to [image 2].' . $this->request->get('description'),
                entity: EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI,
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
                entity: EntityEnum::FLUX_PRO_KONTEXT_MAX_MULTI,
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

    public function setRequest(Request $request): NanoBananaService
    {
        $this->request = $request;

        return $this;
    }
}
