<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSellersTable extends Migration
{
    public function up()
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->string('password');

            // ✅ Role: seller or buyer
            $table->string('role')->default('seller');

            // ✅ Email Verification via Code
            $table->boolean('verified')->default(false); // true if code is verified
            $table->string('verification_code')->nullable(); // 6-digit code

            // ✅ Profile
            $table->boolean('profile_updated')->default(false); // true if profile is filled

            // ✅ Professional category: doctor, nurse, etc.
            $table->string('profession')->nullable(); // optional, filled later
            $table->boolean('is_professional')->default(false); // true if professional body
            $table->boolean('status')->default(false); // admin-verified

            // ✅ Subscription
            $table->boolean('is_subscribed')->default(false); // true if subscribed
            $table->string('subscription_type')->nullable(); // e.g., monthly, yearly
            $table->timestamp('subscription_expires_at')->nullable();

            // Laravel default fields
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sellers');
    }
}
