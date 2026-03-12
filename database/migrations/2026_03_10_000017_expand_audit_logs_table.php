<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN organization_id DROP NOT NULL');
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN actor_id DROP NOT NULL');
        } else {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->uuid('organization_id')->nullable()->change();
                $table->foreignId('actor_id')->nullable()->change();
            });
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_type')
                ->nullable()
                ->after('actor_id');
            $table->string('target_type')
                ->nullable()
                ->after('action');
            $table->string('target_id')
                ->nullable()
                ->after('target_type');
            $table->string('ip_address')
                ->nullable()
                ->after('metadata');
            $table->text('user_agent')
                ->nullable()
                ->after('ip_address');

            $table->index(['organization_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['actor_type', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['actor_type', 'created_at']);
            $table->dropIndex(['target_type', 'target_id']);

            $table->dropColumn([
                'actor_type',
                'target_type',
                'target_id',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};
