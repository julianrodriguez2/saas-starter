<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('organizations', 'pm_type')) {
                $table->string('pm_type')->nullable()->after('stripe_id');
            }

            if (! Schema::hasColumn('organizations', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            }

            $table->index('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);

            if (Schema::hasColumn('organizations', 'pm_last_four')) {
                $table->dropColumn('pm_last_four');
            }

            if (Schema::hasColumn('organizations', 'pm_type')) {
                $table->dropColumn('pm_type');
            }

            if (Schema::hasColumn('organizations', 'stripe_id')) {
                $table->dropUnique('organizations_stripe_id_unique');
                $table->dropColumn('stripe_id');
            }
        });
    }
};
