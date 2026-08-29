<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity');

            // Precio CONGELADO al anadir. No se recalcula al consultar: si el total
            // cambiara solo entre que el usuario lo mira y confirma, habria reclamos.
            $table->unsignedBigInteger('unit_price_cents');

            $table->timestamps();

            // Anadir una variante ya presente SUMA cantidad. Sin esto terminas con
            // "Camisa Azul M x1" tres veces y el usuario no entiende que compro.
            $table->unique(['cart_id', 'product_variant_id'], 'cart_items_cart_variant_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cart_items ADD CONSTRAINT chk_cart_quantity CHECK (quantity BETWEEN 1 AND 20)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
