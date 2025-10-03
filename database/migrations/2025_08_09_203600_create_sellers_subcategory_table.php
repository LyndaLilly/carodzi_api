<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('sellers_subcategory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('sellers_category')->onDelete('cascade');
            $table->string('name');
            $table->boolean('auto_verify')->default(0); // 👈 new column
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sellers_subcategory');
    }
};
