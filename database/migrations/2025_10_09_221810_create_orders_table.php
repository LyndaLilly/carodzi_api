<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Buyer info
            $table->string('buyer_fullname');
            $table->string('buyer_email');
            $table->string('buyer_phone');
            $table->text('buyer_delivery_location');

            // Product info (single product per order)
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('seller_id');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2);
            $table->decimal('total_amount', 12, 2);

            // Payment & status info
            $table->string('payment_method')->default('contact_seller');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->text('bitcoin_proof')->nullable();
            $table->string('paystack_reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('productupload')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
