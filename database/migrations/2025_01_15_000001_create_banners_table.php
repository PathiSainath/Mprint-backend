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
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('price_text')->nullable(); // e.g., "100 Visiting Cards at Rs 200"
            $table->string('button_text')->default('Shop Now');
            $table->string('button_link')->nullable();
            $table->string('image_path');
            $table->enum('type', ['hero', 'promo'])->default('hero'); // hero or promo banner
            $table->enum('position', ['left', 'right', 'full'])->default('left');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
