<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfessionalProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('professional_profiles', function (Blueprint $table) {
            $table->id();

            // Link to seller
            $table->foreignId('seller_id')->constrained('sellers')->onDelete('cascade');

            // Personal info
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
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
         
            // Professional info
            $table->string('verification_number')->nullable();      
            $table->string('school_name')->nullable();
            $table->year('graduation_year')->nullable();
            $table->integer('experience_years')->nullable();          
            $table->string('certificate_file')->nullable();  
               
            // Bank details
            $table->string('bank_name')->nullable();
            $table->string('business_bank_name')->nullable();
            $table->string('business_bank_account')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('professional_profiles');
    }
}
