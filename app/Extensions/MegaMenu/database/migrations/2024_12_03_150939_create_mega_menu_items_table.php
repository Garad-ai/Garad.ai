<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_mega_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mega_menu_id')->constrained('ext_mega_menus')->cascadeOnDelete();
            $table->bigInteger('parent_id')->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->nullable();
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('link')->nullable();
            $table->json('params')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mega_menu_items');
    }
};
