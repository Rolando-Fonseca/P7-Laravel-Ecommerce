<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // UUID y no id autoincremental: con 1, 2, 3 cualquiera lee el carrito
            // de otra persona incrementando el numero.
            $table->uuid('token')->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('COP');
            $table->string('status', 20)->default('open');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
