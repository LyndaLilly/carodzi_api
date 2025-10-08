<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Buyer (customer)
            $table->foreignId('buyer_id')->nullable()->constrained('buyers')->onDelete('set null');

            // Seller (product owner)
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->onDelete('set null');

            // Product (from productupload)
            $table->foreignId('product_id')->nullable()->constrained('productupload')->onDelete('set null');
            $table->string('product_name');
            $table->decimal('product_price', 10, 2);
            $table->integer('quantity')->default(1);

            // Delivery info
            $table->string('delivery_address')->nullable();
            $table->string('delivery_location')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);

            // Payment info (Paystack or Crypto)
            $table->string('payment_reference')->nullable(); // Paystack ref or crypto tx hash
            $table->enum('payment_method', ['paystack', 'crypto', 'other'])->default('paystack');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->text('crypto_proof')->nullable(); // store hash, link, or screenshot path

            // Admin order management
            $table->enum('order_status', ['pending', 'completed', 'rejected'])->default('pending');

            // Totals
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
