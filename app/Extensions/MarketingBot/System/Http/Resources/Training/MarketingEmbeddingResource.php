<?php

declare(strict_types=1);

namespace App\Extensions\MarketingBot\System\Http\Resources\Training;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class MarketingEmbeddingResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'file'       => $this->file,
            'url'        => $this->url,
            'title'      => $this->title,
            'content'    => $this->content,
            'status'     => $this->trained_at ? 'Trained' : 'Not Trained',
        ];
    }
}
