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
        Schema::create('wishes', function (Blueprint $table) {
            $table->id();

            // foreign key to buyers table
            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('buyers')
                ->onDelete('cascade');

            // foreign key to productupload table
            $table->foreignId('product_id')
                ->constrained('productupload')
                ->onDelete('cascade');

            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishes');
    }
};
