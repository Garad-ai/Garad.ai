<?php

declare(strict_types=1);

namespace App\Extensions\CreativeSuite\System\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CreativeSuiteDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'uuid'        => $this->uuid,
            'name'        => $this->name,
            'preview'     => '/uploads/' . $this->preview,
            'preview_url' => $this->preview ? Storage::disk('uploads')->url($this->preview) : null,
            'payload'     => $this->payload,
        ];
    }
}
