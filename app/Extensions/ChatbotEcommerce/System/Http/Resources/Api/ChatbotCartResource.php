<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ChatbotCartResource extends JsonResource
{
    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        // TODO: Uncomment and return actual data later
        return [
            // 'id'                  => $this->getAttribute('id'),
            // 'chatbot_id'          => $this->getAttribute('chatbot_id'),
            // 'chatbot_customer_id' => $this->getAttribute('chatbot_customer_id'),
            // 'session_id'          => $this->getAttribute('session_id'),
            // 'product_source'      => $this->getAttribute('product_source'),
            // 'product_data'        => $this->getAttribute('product_data'),
            'products'            => $this->getAttribute('products'),
            // 'updated_at'          => $this->getAttribute('session_id'),
            // 'created_at'          => $this->getAttribute('created_at')->timezone($this->timezone()),
        ];
    }

    public function timezone(): array|string
    {
        $timezone = request()->header('x-timezone');

        if (is_string($timezone)) {
            return $timezone;
        }

        return 'UTC';
    }
}
