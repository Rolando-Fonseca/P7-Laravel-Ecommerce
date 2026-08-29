<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Identificador publico NGL-2026-000123. El id autoincremental nunca
            // sale de la base de datos.
            $table->string('number', 20)->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('created');
            $table->string('email', 255);

            $table->unsignedBigInteger('subtotal_cents');

            // ADR-0008: estos tres existen desde el dia uno valiendo 0. Anadirlos
            // despues seria un cambio de forma de la respuesta, y por ADR-0002 eso
            // obliga a subir a v2 por un campo con valor cero.
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('shipping_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);

            $table->unsignedBigInteger('total_cents');
            $table->string('currency', 3)->default('COP');

            $table->json('shipping_address');
            $table->json('billing_address')->nullable();
            $table->string('notes', 500)->nullable();

            // Historial de estados como JSON: en el MVP no se consulta por campo,
            // solo se lee entero con el pedido. Si algun dia hay que filtrar por el,
            // pasa a tabla propia con su ADR.
            $table->json('status_history');

            $table->timestamp('placed_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
