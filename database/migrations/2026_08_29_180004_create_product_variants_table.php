<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Unico globalmente y nunca reutilizable, ni tras archivar la variante:
            // los pedidos historicos guardan este texto y empezarian a mentir.
            $table->string('sku', 40)->unique();

            $table->string('size', 10);
            $table->string('size_system', 20)->default('alpha');
            $table->string('color_name', 60);
            $table->string('color_hex', 7)->nullable();

            // Nullable a proposito: null significa "hereda base_price_cents del
            // producto". Asi la talla XXL puede costar mas sin duplicar el precio
            // en cada una de las otras 19 filas.
            $table->unsignedBigInteger('price_cents')->nullable();

            $table->string('barcode', 32)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index(['size', 'color_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
