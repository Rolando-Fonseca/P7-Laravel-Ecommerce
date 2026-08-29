<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ADR-0004. Sin esta tabla, un doble clic o un reintento por timeout de red
        // crea dos pedidos y reserva el stock dos veces.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->string('endpoint', 160);

            // sha256 del JSON NORMALIZADO, no del texto crudo: un espacio de mas no
            // debe contar como cuerpo distinto.
            $table->string('request_hash', 64);

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['key', 'endpoint'], 'idempotency_keys_key_endpoint_unique');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
