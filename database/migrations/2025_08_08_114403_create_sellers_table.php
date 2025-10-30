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
            $table->integer('views')->default(0);

            // ✅ Email Verification via Code
            $table->boolean('verified')->default(false);
            $table->string('verification_code')->nullable();

            // ✅ Profile
            $table->boolean('profile_updated')->default(false);
            $table->boolean('is_professional')->default(false); // true if professional body
            $table->boolean('status')->default(false);          // admin-verified

            // ✅ Category & Sub-category
            $table->unsignedBigInteger('category_id')->nullable()->after('is_professional');
            $table->unsignedBigInteger('sub_category_id')->nullable()->after('category_id');

            $table->unsignedBigInteger('product_id')->nullable()->after('sub_category_id');
            $table->unsignedBigInteger('sub_product_id')->nullable()->after('product_id');

            // ✅ Subscription
            $table->boolean('is_subscribed')->default(false);
            $table->string('subscription_type')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();

            // ✅ Password reset
            $table->string('password_reset_code')->nullable();
            $table->timestamp('password_reset_sent_at')->nullable();

            // ✅ Foreign keys (optional, if tables exist)
            $table->foreign('category_id')
                ->references('id')->on('seller_category')
                ->onDelete('set null');
            $table->foreign('sub_category_id')
                ->references('id')->on('seller_subcategory')
                ->onDelete('set null');

            $table->foreign('product_id')
                ->references('id')->on('product_categories')
                ->onDelete('set null');
            $table->foreign('sub_product_id')
                ->references('id')->on('product_subcategories')
                ->onDelete('set null');

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
