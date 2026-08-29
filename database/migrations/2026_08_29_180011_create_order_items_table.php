<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Referencia que PUEDE quedar huerfana. Por eso lo de abajo.
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Copia historica en texto. Si en 2027 se renombra el producto, este
            // pedido de 2026 debe seguir diciendo lo que el cliente compro. Un
            // pedido que muestra el nombre actual es un pedido que miente.
            $table->string('sku', 40);
            $table->string('product_name', 160);
            $table->string('variant_label', 120);

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('line_total_cents');

            $table->timestamps();

            $table->index('order_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
