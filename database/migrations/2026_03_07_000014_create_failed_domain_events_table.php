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
        Schema::create('failed_domain_events', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('event_key')->nullable();
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message');
            $table->timestamp('failed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'failed_at']);
            $table->index(['resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_domain_events');
    }
};
