<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            // Link to the product in the 'productupload' table
            $table->unsignedBigInteger('productupload_id');
            $table->foreign('productupload_id')
                ->references('id')
                ->on('productupload')
                ->onDelete('cascade');

            // Buyer who made the review (optional for now)
            $table->unsignedBigInteger('buyer_id')->nullable();

            // Link to the specific order (optional)
            $table->unsignedBigInteger('order_id')->nullable()->unique();

            // Rating and review fields
            $table->tinyInteger('rating')->unsigned(); // 1–5 stars
            $table->text('review')->nullable(); // Optional text

            // Flags
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_visible')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
