<?php

namespace App\Extensions\CreativeSuite\System\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreativeSuiteDocument extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ext_creative_suite_documents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'uuid',
        'name',
        'preview',
        'payload',
    ];

    protected $appends = [
        'preview_url',
    ];

    public function previewUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->preview ? $this->getPreviewUrl() : null,
        );
    }

    private function getPreviewUrl(): ?string
    {
        if (! $this->preview) {
            return null;
        }

        return preg_match('/^https?:\/\//', $this->preview)
            ? $this->preview
            : Storage::disk('uploads')->url($this->preview);
    }
}
