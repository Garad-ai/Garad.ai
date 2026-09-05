<?php

namespace App\Extensions\AIChatProDeepResearch\System\Http\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DrTiptapContent extends Model
{
    use HasFactory;

    protected $table = 'dr_tiptap_contents';

    protected $fillable = ['user_id', 'save_contentable_id', 'save_contentable_type', 'title', 'input', 'output'];

    public function saveContentable(): MorphTo
    {
        return $this->morphTo();
    }
}
