<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhoneCallAgentTrainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'type'   => $this->type,
            'file'   => $this->file,
            'url'    => $this->url,
            'text'   => $this->text,
            'name'   => $this->name,
            'status' => $this->trained_at ? 'Trained' : 'Not Trained',
        ];
    }
}
