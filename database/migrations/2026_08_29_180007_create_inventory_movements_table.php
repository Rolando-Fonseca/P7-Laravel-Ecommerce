<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Libro mayor de inventario. APPEND-ONLY: nunca se actualiza, nunca se borra.
        // Toda modificacion de quantity_on_hand o quantity_reserved escribe aqui en
        // la misma transaccion.
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20);
            $table->integer('quantity_delta');
            $table->string('reason', 255);

            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id', 60)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // Segunda red de idempotencia: aunque falle la capa de aplicacion, el
            // indice unico impide escribir dos veces el mismo movimiento.
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
