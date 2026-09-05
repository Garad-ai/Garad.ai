<?php

use App\Extensions\MarketingBot\System\Enums\EmbeddingTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_marketing_campaign_embeddings')) {
            return;
        }

        Schema::create('ext_marketing_campaign_embeddings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('marketing_campaign_id')->nullable();
            $table->string('engine');
            $table->string('title')->nullable();
            $table->string('file')->nullable();
            $table->string('url')->nullable();
            $table->longText('content')->nullable();
            $table->json('embedding')->nullable();
            $table->string('type')->nullable()->default(EmbeddingTypeEnum::text->value);
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_marketing_campaign_embeddings');
    }
};
