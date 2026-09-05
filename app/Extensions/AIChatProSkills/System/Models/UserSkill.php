<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserSkill extends Pivot
{
    protected $table = 'user_skills';

    protected $casts = [
        'auto_use' => 'boolean',
    ];
}
