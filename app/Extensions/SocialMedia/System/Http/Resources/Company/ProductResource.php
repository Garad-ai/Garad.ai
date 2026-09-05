<?php

declare(strict_types=1);

namespace App\Extensions\SocialMedia\System\Http\Resources\Company;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'type'         => $this->type,
            'description'  => $this->description,
            'key_features' => $this->key_features,
        ];
    }
}
