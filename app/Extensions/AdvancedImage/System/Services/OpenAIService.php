<?php

namespace App\Extensions\AdvancedImage\System\Services;

use App\Services\Ai\OpenAI\Image\CreateImageEditService;
use Illuminate\Http\Request;

class OpenAIService
{
    public string $tool;

    public string $model = 'gpt-image-1';

    public Request $request;

    public function generate(): array
    {
        return match ($this->tool) {
            'reimagine'         => $this->reimagineHandle(),
            'cleanup'           => $this->cleanupHandle(),
            'remove_background' => $this->removeBackgroundHandle(),
            'sketch_to_image'   => $this->sketchToImageHandle(),
            'inpainting'        => $this->inpaintingHandle(),
            'style_transfer'    => $this->styleTransferHandle(),
            'reference_image'   => $this->referenceImageHandle(),
            'remove_text'       => $this->removeTextHandle(),
            default             => [],
        };
    }

    private function removeTextHandle(): array
    {
        $this->request->validate([
            'uploaded_image'   => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $image = 'uploads/' . $this->request->file('uploaded_image')?->store('', ['disk' => 'uploads']);

        return app(CreateImageEditService::class)
            ->setImages([$image])
            ->setModel($this->getModel())
            ->setPrompt('Remove all visible text from the selected area of the image and fill the region with natural background content, matching the surrounding textures and colors.')
            ->generate();
    }

    // Remove all visible text from the selected area of the image and fill the region with natural background content, matching the surrounding textures and colors.
    private function styleTransferHandle(): array
    {
        $this->request->validate([
            'uploaded_image'   => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
            'reference_image'  => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $image = 'uploads/' . $this->request->file('uploaded_image')?->store('', ['disk' => 'uploads']);
        $style = 'uploads/' . $this->request->file('reference_image')?->store('', ['disk' => 'uploads']);

        return app(CreateImageEditService::class)
            ->setImages([$image, $style])
            ->setModel($this->getModel())
            ->setPrompt('Apply style transfer to the image.')
            ->generate();
    }

    private function inpaintingHandle(): array
    {
        $this->request->validate([
            'uploaded_image'   => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $image = 'uploads/' . $this->request->file('uploaded_image')?->store('', ['disk' => 'uploads']);

        return app(CreateImageEditService::class)
            ->setImages([$image])
            ->setModel($this->getModel())
            ->setPrompt('Inpainting : ' . $this->request->input('description'))
            ->generate();
    }

    private function sketchToImageHandle(): array
    {
        $this->request->validate([
            'sketch_file'   => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $image = 'uploads/' . $this->request->file('sketch_file')?->store('', ['disk' => 'uploads']);

        return app(CreateImageEditService::class)
            ->setImages([$image])
            ->setModel($this->getModel())
            ->setPrompt('sketch to image : ' . $this->request->input('description'))
            ->generate();
    }

    private function removeBackgroundHandle(): array
    {
        $this->request->validate([
            'uploaded_image'   => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $image = 'uploads/' . $this->request->file('uploaded_image')?->store('', ['disk' => 'uploads']);

        return app(CreateImageEditService::class)
            ->setImages([$image])
            ->setModel($this->getModel())
            ->setPrompt('Remove the background of the image and make it transparent.')
            ->generate();
    }

    private function cleanupHandle(): array
    {
        $this->request->validate([
            'uploaded_image'   => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
            'mask_file'        => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $image = 'uploads/' . $this->request->file('uploaded_image')?->store('', ['disk' => 'uploads']);

        $mask = 'uploads/' . $this->request->file('mask_file')?->store('', ['disk' => 'uploads']);

        return app(CreateImageEditService::class)
            ->setImages([$image])
            ->setMask($mask)
            ->setModel($this->getModel())
            ->setPrompt('Remove the areas marked in the mask from the image. Keep the rest of the image intact.')
            ->generate();
    }

    private function reimagineHandle(): array
    {
        $this->request->validate([
            'images'   => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);

        $images = $this->request->file('images');

        $imagePaths = [];

        foreach ($images as $image) {
            $imagePaths[] = 'uploads/' . $image->store('', ['disk' => 'uploads']);
        }

        if (count($imagePaths) === 0) {
            return [
                'status'  => false,
                'message' => 'No images uploaded.',
            ];
        }

        return app(CreateImageEditService::class)
            ->setImages($imagePaths)
            ->setModel($this->getModel())
            ->setPrompt($this->request->input('description'))
            ->generate();
    }

    public function setTool(string $tool): self
    {
        $this->tool = $tool;

        return $this;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
