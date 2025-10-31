<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('direct_inquiries', function (Blueprint $table) {
            $table->id();

            // Link to sellers & buyers tables
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('buyer_id')->nullable();

            // Link to a product (optional)
            $table->unsignedBigInteger('product_id')->nullable();

            // Inquiry details
            $table->string('contact_method'); // whatsapp, call, email
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->text('message')->nullable();

            // ✅ New fields for tracking professional work progress
            $table->enum('status', ['pending', 'in_progress', 'completed', 'not_completed'])
                  ->default('pending');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Foreign key relationships
            $table->foreign('seller_id')
                ->references('id')
                ->on('sellers')
                ->onDelete('cascade');

            $table->foreign('buyer_id')
                ->references('id')
                ->on('buyers')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_inquiries');
    }
};
