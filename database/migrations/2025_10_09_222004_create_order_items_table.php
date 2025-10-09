<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderItemsTable extends Migration
{
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('seller_id'); // ← vendor for this product

            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2);

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('productupload')->onDelete('cascade');
            $table->foreign('seller_id')->references('id')->on('sellers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_items');
    }
}
