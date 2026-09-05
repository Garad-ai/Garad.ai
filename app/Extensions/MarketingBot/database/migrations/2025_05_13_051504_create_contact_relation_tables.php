<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_contact_list_segment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('ext_contact_lists')->cascadeOnDelete();
            $table->foreignId('segment_id')->constrained('ext_segments')->cascadeOnDelete();
        });

        Schema::create('ext_contact_list_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('ext_contact_lists')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('ext_contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_contact_list_segment');
        Schema::dropIfExists('ext_contact_list_contact');
    }
};
