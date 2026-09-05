<?php

namespace App\Extensions\AIImagePro\System\Models;

use App\Enums\AiImageStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AiImageProModel extends Model
{
    use HasFactory;

    protected $table = 'ai_image_pro';

    protected $fillable = [
        'user_id',
        'guest_ip',
        'model',
        'engine',
        'prompt',
        'params',
        'status',
        'generated_images',
        'image_width',
        'image_height',
        'metadata',
        'published',
        'likes_count',
        'views_count',
        'started_at',
        'completed_at',
        'publish_requested_at',
        'publish_reviewed_at',
        'publish_reviewed_by',
        'share_token',
    ];

    protected $casts = [
        'params'                => 'array',
        'generated_images'      => 'array',
        'image_width'           => 'integer',
        'image_height'          => 'integer',
        'metadata'              => 'array',
        'status'                => AiImageStatusEnum::class,
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
        'published'             => 'boolean',
        'likes_count'           => 'integer',
        'views_count'           => 'integer',
        'publish_requested_at'  => 'datetime',
        'publish_reviewed_at'   => 'datetime',
    ];

    /**
     * Get the user who created this image.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who reviewed the publish request.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publish_reviewed_by');
    }

    /**
     * Get all likes for this image.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(AiImageProLikeModel::class, 'ai_image_pro_id');
    }

    /**
     * Check if the current user/guest has liked this image.
     */
    public function isLikedBy($userIdOrIp = null): bool
    {
        if ($userIdOrIp === null) {
            $userIdOrIp = auth()->check() ? auth()->id() : request()->ip();
        }

        if (is_numeric($userIdOrIp)) {
            return $this->likes()->where('user_id', $userIdOrIp)->exists();
        }

        return $this->likes()->where('guest_ip', $userIdOrIp)->exists();
    }

    /**
     * Toggle like for this image.
     */
    public function toggleLike($userId = null, $guestIp = null): bool
    {
        if ($userId) {
            $like = $this->likes()->where('user_id', $userId)->first();
        } else {
            $like = $this->likes()->where('guest_ip', $guestIp)->first();
        }

        if ($like) {
            // Unlike
            $like->delete();
            if ($this->likes_count > 0) {
                $this->decrement('likes_count');
            }

            return false;
        }

        $this->likes()->create([
            'user_id'  => $userId,
            'guest_ip' => $guestIp,
        ]);
        $this->increment('likes_count');

        return true;
    }

    /**
     * Increment views count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
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

    /**
     * Save the actual image dimensions from the first generated image file.
     *
     * Reads dimensions from disk using getimagesize() for images,
     * or getimagesizefromstring() for binary data. Skips videos as
     * they require ffprobe which may not be available.
     *
     * @param  string|null  $storagePath  A relative path inside the public disk (e.g. "media/images/u-1/img_xxx.png").
     *                                    If null, attempts to resolve from the first generated_images entry.
     */
    public function saveDimensions(?string $storagePath = null): void
    {
        if ($this->image_width && $this->image_height) {
            return;
        }

        try {
            $storagePath = $storagePath ?? $this->resolveFirstStoragePath();

            if (! $storagePath) {
                return;
            }

            $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
            $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv'];

            if (in_array($extension, $videoExtensions, true)) {
                return;
            }

            $fullPath = Storage::disk('public')->path($storagePath);

            if (! file_exists($fullPath)) {
                return;
            }

            $size = getimagesize($fullPath);

            if ($size === false) {
                return;
            }

            $this->update([
                'image_width'  => $size[0],
                'image_height' => $size[1],
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to read image dimensions', [
                'record_id' => $this->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the storage-relative path from the first generated image URL.
     *
     * Generated images are stored as "/uploads/media/images/u-1/img_xxx.png".
     * The public disk root maps to "storage/app/public", so the storage-relative
     * path is everything after "/uploads/".
     */
    private function resolveFirstStoragePath(): ?string
    {
        $images = $this->generated_images ?? [];

        if (empty($images)) {
            return null;
        }

        $url = $images[0];

        if (str_starts_with($url, '/uploads/')) {
            return substr($url, strlen('/uploads/'));
        }

        return ltrim($url, '/');
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status'       => AiImageStatusEnum::FAILED,
            'metadata'     => array_merge($this->metadata ?? [], ['error' => $error]),
            'completed_at' => now(),
        ]);
    }

    /**
     * Request to publish this image to the community.
     */
    public function requestPublish(): void
    {
        $this->update([
            'publish_requested_at' => now(),
        ]);
    }

    /**
     * Check if this image has a pending publish request.
     */
    public function hasPendingPublishRequest(): bool
    {
        return ! is_null($this->publish_requested_at) && is_null($this->publish_reviewed_at);
    }

    /**
     * Check if this image publish request was approved.
     */
    public function isPublishApproved(): bool
    {
        return $this->published && ! is_null($this->publish_reviewed_at);
    }

    /**
     * Check if this image publish request was rejected.
     */
    public function isPublishRejected(): bool
    {
        return ! $this->published && ! is_null($this->publish_reviewed_at);
    }

    /**
     * Check if this image was generated using a tool.
     */
    public function isToolGenerated(): bool
    {
        return ! empty($this->metadata['tool_id']) || ! empty($this->metadata['tool_name']);
    }

    /**
     * Get the tool information if this was tool-generated.
     */
    public function getToolInfo(): ?array
    {
        if (! $this->isToolGenerated()) {
            return null;
        }

        return [
            'id'     => $this->metadata['tool_id'] ?? null,
            'name'   => $this->metadata['tool_name'] ?? null,
            'inputs' => $this->metadata['tool_inputs'] ?? [],
        ];
    }

    /**
     * Store tool metadata.
     */
    public function setToolMetadata(int $toolId, string $toolName, array $inputs): self
    {
        $metadata = $this->metadata ?? [];
        $metadata['tool_id'] = $toolId;
        $metadata['tool_name'] = $toolName;
        $metadata['tool_inputs'] = $inputs;

        $this->update([
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);

        return $this;
    }
}
