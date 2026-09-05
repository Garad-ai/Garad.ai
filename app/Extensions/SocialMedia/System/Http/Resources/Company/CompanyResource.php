<?php

declare(strict_types=1);

namespace App\Extensions\SocialMedia\System\Http\Resources\Company;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'brand_color' => $this->brand_color,
            'products'    => ProductResource::collection($this->products),
        ];
    }
}
