<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ugc_factory_actors')) {
            return;
        }

        Schema::create('ugc_factory_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // preset | upload | generated
            $table->string('name', 160);
            $table->string('image_path')->nullable();
            $table->string('preset_key', 120)->nullable();
            $table->text('prompt')->nullable();
            $table->string('status', 32)->default('ready'); // pending | ready | failed
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ugc_factory_actors');
    }
};
