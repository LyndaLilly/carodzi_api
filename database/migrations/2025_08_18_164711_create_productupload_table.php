<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productupload', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade');
            $table->string('name');
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('cascade');
            $table->foreignId('subcategory_id')->constrained('product_subcategories')->onDelete('cascade');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('condition')->nullable();
            $table->string('internal_storage')->nullable();
            $table->string('ram')->nullable();
            $table->string('location');
         

            // ✅ Price is now nullable (professionals may not use it)
            $table->decimal('price', 12, 2)->nullable();

            $table->text('description')->nullable();

            // ✅ New fields required by your controller
            $table->string('currency', 10)->nullable();
            $table->string('specialization')->nullable();
            $table->string('availability')->nullable();
            $table->string('rate', 50)->nullable();
            $table->unsignedBigInteger('views')->default(0);


            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('productupload_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('productupload_id')
                ->constrained('productupload')
                ->onDelete('cascade');
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productupload_images');
        Schema::dropIfExists('productupload');
    }
};
