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
            
            $table->unsignedBigInteger('buyer_id'); // buyer placing the order
            
            // Buyer delivery information
            $table->string('delivery_fullname');
            $table->string('delivery_email');
            $table->string('delivery_phone');
            $table->text('delivery_location'); // can include address, city, etc.
            
            $table->decimal('total_amount', 12, 2); // total price of all items
            
            $table->enum('payment_method', ['paystack', 'bitcoin']);
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            
            $table->text('bitcoin_proof')->nullable(); // for bitcoin: hash or screenshot URL
            $table->string('paystack_reference')->nullable(); // for paystack payments
            
            $table->text('notes')->nullable(); // optional buyer notes
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('buyer_id')->references('id')->on('buyers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
