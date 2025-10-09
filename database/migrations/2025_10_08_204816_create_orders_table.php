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
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('productupload')->onDelete('cascade');

            $table->string('product_name');
            $table->decimal('product_price', 15, 2);
            $table->integer('quantity')->default(1);

            $table->text('delivery_address');
            $table->string('delivery_location')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);

            $table->enum('payment_method', ['paystack', 'crypto', 'other'])->default('other');
            $table->string('payment_reference')->nullable();  // paystack reference
            $table->string('crypto_proof')->nullable();       // bitcoin hash or uploaded proof

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
