<?php

use App\Extensions\AIChatProSkills\database\seeders\SkillCreatorSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description');
            $table->longText('instructions');
            $table->json('bundled_resources')->nullable();
            $table->enum('source', ['uploaded', 'github', 'created', 'seeded'])->default('created');
            $table->string('source_url')->nullable();
            $table->boolean('is_public')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['slug', 'user_id']);
        });

        app(SkillCreatorSeeder::class)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
