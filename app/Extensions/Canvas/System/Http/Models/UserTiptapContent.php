<?php

namespace App\Extensions\Canvas\System\Http\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserTiptapContent extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'save_contentable_id', 'save_contentable_type', 'title', 'input', 'output'];

    public function saveContentable(): MorphTo
    {
        return $this->morphTo();
    }
}
