<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Http\Controllers;

use App\Http\Controllers\Controller;

class AIChatProSkillsController extends Controller
{
    public function __invoke()
    {
        return view('ai-chat-pro-skills::index');
    }
}
