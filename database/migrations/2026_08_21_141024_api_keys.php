<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id('id_api');
            $table->foreignId('id_provider')
                ->constrained(
                    table: 'ai_providers',
                    column: 'id_provider'
                )
                ->onUpdate('restrict')
                ->onDelete('cascade');
            $table->string('name', 100);
            $table->text('encrypted_key');
            $table->enum('status', [
                'ready',
                'cooldown',
                'disabled'
            ])->default('ready');
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('rate_limit')->default(60);
            $table->timestamp('cooldown_until')->nullable();
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['id_provider', 'priority']);
            $table->index(['status', 'cooldown_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
