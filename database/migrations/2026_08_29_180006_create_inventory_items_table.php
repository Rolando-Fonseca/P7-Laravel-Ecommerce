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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->timestamps();

            // Sin esto aparecen filas duplicadas para la misma variante en el mismo
            // almacen y el stock se cuenta dos veces.
            $table->unique(['product_variant_id', 'warehouse_id'], 'inventory_items_variant_warehouse_unique');
        });

        // available = on_hand - reserved es un campo CALCULADO, nunca una columna.
        // Si fuera columna seria un tercer dato que puede desincronizarse de los
        // otros dos, y no habria forma de saber cual de los tres miente.
        //
        // Esta restriccion es la ultima red de ADR-0006: si una refactorizacion
        // futura olvida el lockForUpdate, la base de datos rechaza la escritura.
        // SQLite no permite ALTER TABLE ADD CONSTRAINT, por eso va condicionada
        // al driver (ver ADR-0010).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT chk_reserved_lte_on_hand CHECK (quantity_reserved <= quantity_on_hand)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
