<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellerProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('seller_id')
                  ->constrained('sellers')
                  ->onDelete('cascade');

            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('country')->nullable();
            $table->string('whatsapp_phone_link')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('business_name')->nullable();

            // ✅ Profession moved here
            $table->string('profession')->nullable();

            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('product_service_id')->nullable();

            // ✅ Profile image column
            $table->string('profile_image')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('seller_profiles');
    }
}
