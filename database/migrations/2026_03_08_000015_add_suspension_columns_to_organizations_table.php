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
            $table->boolean('is_suspended')
                ->default(false)
                ->after('trial_ends_at');
            $table->timestamp('suspended_at')
                ->nullable()
                ->after('is_suspended');
            $table->text('suspension_reason')
                ->nullable()
                ->after('suspended_at');

            $table->index('is_suspended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['is_suspended']);
            $table->dropColumn([
                'is_suspended',
                'suspended_at',
                'suspension_reason',
            ]);
        });
    }
};
