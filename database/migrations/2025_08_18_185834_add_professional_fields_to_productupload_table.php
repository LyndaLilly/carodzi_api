<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productupload', function (Blueprint $table) {
            // Extra service fields
            $table->string('specialization')->nullable()->after('ram'); 
            $table->string('qualification')->nullable()->after('specialization'); 
            $table->string('availability')->nullable()->after('qualification'); 
            $table->decimal('rate', 12, 2)->nullable()->after('availability'); 
        });
    }

    public function down(): void
    {
        Schema::table('productupload', function (Blueprint $table) {
            $table->dropColumn([
                'specialization',
                'qualification',
                'availability',
                'rate',
            ]);
        });
    }
};
