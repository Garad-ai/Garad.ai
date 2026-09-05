<?php

use App\Models\Common\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Menu::query()->firstOrCreate([
            'key' => 'video_editor_divider',
        ], [
            'parent_id'  => null,
            'route'      => null,
            'label'      => null,
            'icon'       => null,
            'svg'        => null,
            'order'      => 27,
            'is_active'  => true,
            'params'     => null,
            'type'       => 'divider',
            'extension'  => true,
        ]);

        Menu::query()->firstOrCreate([
            'key' => 'video_editor_label',
        ], [
            'parent_id'  => null,
            'route'      => null,
            'label'      => 'Video Editor',
            'icon'       => null,
            'svg'        => null,
            'order'      => 27,
            'is_active'  => true,
            'params'     => null,
            'type'       => 'label',
            'extension'  => true,
        ]);

        Menu::query()->firstOrCreate([
            'key' => 'video_editor',
        ], [
            'parent_id'  => null,
            'route'      => 'dashboard.user.video-editor.index',
            'label'      => 'Video Editor',
            'icon'       => 'tabler-movie',
            'svg'        => null,
            'order'      => 27,
            'is_active'  => true,
            'params'     => null,
            'type'       => 'item',
            'extension'  => true,
        ]);

        Cache::forget('dynamic_menu_key');
        Cache::forget('dynamic_menu_key_active');
    }

    public function down(): void
    {
        Menu::query()->whereIn('key', [
            'video_editor_divider',
            'video_editor_label',
            'video_editor',
        ])->delete();

        Cache::forget('dynamic_menu_key');
        Cache::forget('dynamic_menu_key_active');
    }
};
