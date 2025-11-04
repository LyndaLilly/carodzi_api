<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOtherProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('other_profiles', function (Blueprint $table) {
            $table->id();

            // Link to seller
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade');

            // Personal info
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('date_of_birth', 15)->nullable();
            $table->string('profile_image')->nullable();  
                
            // About section
            $table->text('about')->nullable();
      
            // Business info 
            $table->string('business_email')->unique()->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('whatsapp_phone_link')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('business_name')->nullable();
            $table->date('date_of_establishment')->nullable();
        
               
            // Bank details
            $table->string('bank_name')->nullable();
            $table->string('business_bank_name')->nullable();
            $table->string('business_bank_account')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('other_profiles');
    }
}
