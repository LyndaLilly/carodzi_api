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
    Schema::create('chat_intents', function (Blueprint $table) {
        $table->id();
        $table->string('token', 64)->unique();
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('seller_id');
        $table->unsignedInteger('qty')->default(1);
        $table->text('message')->nullable();
        $table->timestamp('expires_at');
        $table->timestamp('used_at')->nullable();
        $table->timestamps();

        $table->index(['seller_id', 'product_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_intents');
    }
};
