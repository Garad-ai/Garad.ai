<?php

namespace App\Extensions\AiChatProImageChat\System\Models;

use App\Enums\AiImageStatusEnum;
use App\Models\User;
use App\Models\UserOpenaiChatMessage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiChatProImageModel extends Model
{
    use HasFactory;

    protected $table = 'ai_chat_pro_image';

    protected $fillable = [
        'user_id',
        'message_id',
        'guest_ip',
        'model',
        'engine',
        'request_id',
        'prompt',
        'params',
        'status',
        'generated_images',
        'metadata',
        'suggestions_response',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'params'                => 'array',
        'generated_images'      => 'array',
        'metadata'              => 'array',
        'status'                => AiImageStatusEnum::class,
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
        'suggestions_response'  => 'array',
    ];

    /**
     * Get the user who created this image.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the chat message this image belongs to.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(UserOpenaiChatMessage::class, 'message_id');
    }

    /**
     * Accessors for timing and status formatting
     */
    public function isPending(): bool
    {
        return $this->status === AiImageStatusEnum::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === AiImageStatusEnum::COMPLETED;
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status'     => AiImageStatusEnum::PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $images, array $metadata = []): void
    {
        DB::transaction(function () use ($images, $metadata) {
            $record = self::where('id', $this->id)->lockForUpdate()->first();

            $existing = $record->generated_images ?? [];

            // Merge old + new images
            $allImages = array_merge($existing, $images);

            $expectedCount = $record->params['image_count'] ?? 1;

            $isComplete = count($allImages) >= $expectedCount;

            // Update model safely inside the transaction
            $record->update([
                'generated_images' => $allImages,
                'status'           => $isComplete
                    ? AiImageStatusEnum::COMPLETED
                    : AiImageStatusEnum::PROCESSING,
                'metadata'     => array_merge($record->metadata ?? [], $metadata),
                'completed_at' => $isComplete ? now() : null,
            ]);
        });
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status'       => AiImageStatusEnum::FAILED,
            'metadata'     => array_merge($this->metadata ?? [], ['error' => $error]),
            'completed_at' => now(),
        ]);
    }
}
