<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyersTable extends Migration
{
    public function up()
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');

            // ✅ Role:  buyer
            $table->string('role')->default('buyer');

            // ✅ Email Verification via Code
            $table->boolean('verified')->default(false);
            $table->string('verification_code')->nullable();

            // ✅ Profile
            $table->boolean('profile_updated')->default(false);


            // ✅ Password reset
            $table->string('password_reset_code')->nullable();
            $table->timestamp('password_reset_sent_at')->nullable();

            // Laravel default fields
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('buyers');
    }
}
