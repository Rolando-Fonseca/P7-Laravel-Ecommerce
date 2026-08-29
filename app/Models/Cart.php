<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CartStatus;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    /**
     * El precio de una linea se congela al anadirla. La caducidad es lo que impide
     * que ese precio viva para siempre.
     */
    public const LIFETIME_DAYS = 14;

    protected $fillable = ['token', 'user_id', 'currency', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['status' => CartStatus::class, 'expires_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function subtotalCents(): int
    {
        return (int) $this->items->sum(
            fn (CartItem $item): int => $item->lineTotalCents()
        );
    }

    /**
     * ADR-0008: la formula se escribe con las cuatro operaciones aunque hoy los tres
     * sumandos valgan cero. El dia que dejen de valer cero no hay que refactorizar.
     */
    public function totalCents(): int
    {
        return $this->subtotalCents()
            - $this->discountCents()
            + $this->shippingCents()
            + $this->taxCents();
    }

    public function discountCents(): int
    {
        return 0;
    }

    public function shippingCents(): int
    {
        return 0;
    }

    public function taxCents(): int
    {
        return 0;
    }

    /**
     * Suma de cantidades, no numero de lineas. Es lo que va en la burbuja del icono
     * del carrito.
     */
    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
