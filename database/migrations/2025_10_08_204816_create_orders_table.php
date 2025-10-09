<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_id')->constrained('buyers')->onDelete('cascade');
            
            // No product_id here — handled by order_items
            $table->text('delivery_address');
            $table->string('delivery_location')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);

            $table->enum('payment_method', ['paystack', 'crypto', 'other'])->default('other');
            $table->string('payment_reference')->nullable();
            $table->string('crypto_proof')->nullable();

            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('order_status', ['pending', 'processing', 'completed', 'rejected'])->default('pending');

            $table->decimal('total_amount', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
