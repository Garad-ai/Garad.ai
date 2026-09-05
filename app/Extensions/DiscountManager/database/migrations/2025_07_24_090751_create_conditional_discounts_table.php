<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 'user_type', 'payment_gateway', 'pricing_plans', 'allow_once_per_user', 'active', 'scheduled', 'start_date', 'end_date'];
        Schema::create('conditional_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('title')->nullable();
            $table->string('condition');
            $table->string('type');
            $table->float('amount');
            $table->string('duration');
            $table->integer('total_usage_limit');
            $table->boolean('show_strikethrough_price')->default(true);
            $table->string('user_type')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('pricing_plans')->nullable();
            $table->boolean('allow_once_per_user')->default(true);
            $table->boolean('active')->default(false);
            $table->boolean('scheduled')->default(false);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();

            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conditional_discounts');
    }
};
