<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotes', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade');

            // Promotion details
            $table->string('plan'); // e.g. 'basic', 'standard', 'premium'
            $table->integer('duration'); // in days
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();

            // Status
            $table->boolean('is_active')->default(false);
            $table->boolean('is_approved')->default(false); // manual approval for crypto payments

            // Payment
            $table->enum('payment_method', ['paystack', 'crypto'])->nullable();
            $table->string('transaction_reference')->nullable(); // for Paystack
            $table->string('crypto_hash')->nullable(); // uploaded hash for crypto
            $table->decimal('amount', 10, 2)->nullable();

            // Expiration
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotes');
    }
};
