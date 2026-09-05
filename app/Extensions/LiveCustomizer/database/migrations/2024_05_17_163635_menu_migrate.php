<?php

use App\Models\Common\Menu;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->runningUnitTests()) {
            Menu::query()
                ->where('key', 'live_customizer')
                ->update([
                    'parent_id' => null,
                    'order'     => 69,
                    'icon'      => 'tabler-brush',
                ]);
        }
    }

    public function down(): void {}
};
