<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Requests;

use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use Illuminate\Foundation\Http\FormRequest;

class StoreActorUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:120',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:' . UGCFactoryRegistry::getMaxUploadSizeKb(),
        ];
    }
}
