<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            // El slug es el identificador público y se congela: cambiar el nombre
            // no lo cambia, porque romperia enlaces ya publicados.
            $table->string('slug', 160)->unique();
            $table->string('name', 160);
            $table->string('summary', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('material', 160)->nullable();
            $table->string('care_instructions', 255)->nullable();

            // Dinero en centavos. Nunca float: 0.10 no es representable en binario.
            $table->unsignedBigInteger('base_price_cents');
            $table->string('currency', 3)->default('COP');

            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
