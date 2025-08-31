<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('buyer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_id');
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth');
            $table->string('profile_image')->nullable();
            $table->text('about')->nullable(); // optional
            $table->string('email')->unique();
            $table->string('mobile_number');
            $table->string('whatsapp_phone_link')->nullable(); // optional
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->timestamps();

            $table->foreign('buyer_id')->references('id')->on('buyers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('buyer_profiles');
    }
};
