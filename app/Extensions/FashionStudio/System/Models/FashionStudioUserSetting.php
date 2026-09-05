<?php

declare(strict_types=1);

namespace App\Extensions\FashionStudio\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FashionStudioUserSetting extends Model
{
    protected $table = 'fashion_studio_user_settings';

    protected $fillable = [
        'user_id',
        'num_images',
        'resolution',
        'ratio',
    ];

    protected $casts = [
        'num_images' => 'integer',
    ];

    public const DEFAULT_NUM_IMAGES = 2;

    public const MAX_NUM_IMAGES = 4;

    public const DEFAULT_RESOLUTION = '1K';

    public const DEFAULT_RATIO = '16:9';

    public const RESOLUTIONS = ['1K', '2K', '4K'];

    public const RATIOS = ['auto', '21:9', '16:9', '3:2', '4:3', '5:4', '1:1', '4:5', '3:4', '2:3', '9:16'];

    /**
     * Resolution to pixel dimensions mapping
     */
    public const RESOLUTION_DIMENSIONS = [
        '1K' => ['width' => 1024, 'height' => 1024],
        '2K' => ['width' => 2048, 'height' => 2048],
        '4K' => ['width' => 4096, 'height' => 4096],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'num_images'  => self::DEFAULT_NUM_IMAGES,
                'resolution'  => self::DEFAULT_RESOLUTION,
                'ratio'       => self::DEFAULT_RATIO,
            ]
        );
    }

    /**
     * Get the image size based on resolution and ratio.
     *
     * Returns an empty array when the ratio is 'auto' (or otherwise not a
     * valid `W:H` pair) so callers can omit `image_size` from the upstream
     * request and let the provider pick the dimensions.
     */
    public function getImageSize(): array
    {
        if ($this->ratio === 'auto') {
            return [];
        }

        $baseDimensions = self::RESOLUTION_DIMENSIONS[$this->resolution] ?? self::RESOLUTION_DIMENSIONS[self::DEFAULT_RESOLUTION];
        $ratioParts = explode(':', $this->ratio);

        if (count($ratioParts) !== 2) {
            return [];
        }

        $ratioWidth = (int) $ratioParts[0];
        $ratioHeight = (int) $ratioParts[1];

        if ($ratioWidth >= $ratioHeight) {
            $width = $baseDimensions['width'];
            $height = (int) round($width * $ratioHeight / $ratioWidth);
        } else {
            $height = $baseDimensions['height'];
            $width = (int) round($height * $ratioWidth / $ratioHeight);
        }

        return ['width' => $width, 'height' => $height];
    }
}
