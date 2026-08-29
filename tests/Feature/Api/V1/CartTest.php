<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

class CartTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    public function test_crear_carrito_devuelve_token_y_los_cinco_campos_de_totales(): void
    {
        // ADR-0008: descuento, envio e impuesto estan desde el dia uno valiendo 0.
        $response = $this->postJson('/api/v1/carts')->assertStatus(201);

        $response->assertJsonStructure([
            'data' => ['token', 'status', 'currency', 'items',
                'totals' => ['subtotal_cents', 'discount_cents', 'shipping_cents', 'tax_cents', 'total_cents'],
                'item_count', 'expires_at'],
        ]);

        $response->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.totals.total_cents', 0)
            ->assertJsonPath('data.totals.tax_cents', 0)
            ->assertHeader('Location');
    }

    public function test_anadir_la_misma_variante_suma_cantidad_en_vez_de_crear_otra_linea(): void
    {
        $variant = $this->variantWithStock(onHand: 20);
        $cart = Cart::factory()->create();

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 2]);
        $response = $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 3]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.item_count', 5);
    }

    public function test_la_suma_que_supera_el_tope_por_linea_devuelve_422(): void
    {
        $variant = $this->variantWithStock(onHand: 100);
        $cart = Cart::factory()->create();

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 15]);

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 10])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_anadir_al_carrito_no_valida_stock(): void
    {
        // ADR-0005: el carrito no compromete inventario, asi que no puede fallar por
        // falta de el. Se pueden anadir 10 de las que quedan 2.
        $variant = $this->variantWithStock(onHand: 2);
        $cart = Cart::factory()->create();

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 10])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 10)
            ->assertJsonPath('data.items.0.available', 2)
            ->assertJsonPath('data.items.0.has_enough_stock', false);
    }

    public function test_una_variante_archivada_devuelve_409(): void
    {
        $variant = $this->variantWithStock(variantAttributes: ['status' => 'archived']);
        $cart = Cart::factory()->create();

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 1])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'VARIANT_UNAVAILABLE');
    }

    public function test_un_carrito_ya_convertido_no_admite_mas_lineas(): void
    {
        // Sin este estado, un doble clic en confirmar seguido de anadir item
        // corrompe un pedido ya creado.
        $variant = $this->variantWithStock();
        $cart = Cart::factory()->converted()->create();

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 1])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CART_NOT_MODIFIABLE');
    }

    public function test_un_carrito_caducado_devuelve_410(): void
    {
        $cart = Cart::factory()->expired()->create();

        $this->getJson("/api/v1/carts/{$cart->token}")
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'CART_EXPIRED');
    }

    public function test_el_precio_de_la_linea_no_se_recalcula_al_consultar(): void
    {
        // Si el total cambiara solo entre que el usuario lo mira y confirma, habria
        // reclamos. El precio se congela al anadir.
        $variant = $this->variantWithStock(onHand: 10, productAttributes: ['base_price_cents' => 1_000_000]);
        $cart = Cart::factory()->create();

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $variant->sku, 'quantity' => 1]);

        $variant->product->update(['base_price_cents' => 9_999_999]);

        $this->getJson("/api/v1/carts/{$cart->token}")
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price_cents', 1_000_000)
            ->assertJsonPath('data.totals.total_cents', 1_000_000);
    }

    public function test_una_linea_de_otro_carrito_devuelve_404_y_no_403(): void
    {
        // Un 403 confirmaria que la linea existe y permitiria enumerar el contenido
        // de otros carritos.
        $variant = $this->variantWithStock();
        $otro = $this->cartWith($variant);
        $mio = Cart::factory()->create();

        $itemId = CartItem::where('cart_id', $otro->id)->value('id');

        $this->patchJson("/api/v1/carts/{$mio->token}/items/{$itemId}", ['quantity' => 3])
            ->assertStatus(404);
    }

    public function test_actualizar_reemplaza_la_cantidad_y_no_la_suma(): void
    {
        $variant = $this->variantWithStock(onHand: 20);
        $cart = $this->cartWith($variant, quantity: 5);
        $itemId = CartItem::where('cart_id', $cart->id)->value('id');

        $this->patchJson("/api/v1/carts/{$cart->token}/items/{$itemId}", ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_eliminar_una_linea_devuelve_el_carrito_con_los_totales_recalculados(): void
    {
        $variant = $this->variantWithStock();
        $cart = $this->cartWith($variant, quantity: 2);
        $itemId = CartItem::where('cart_id', $cart->id)->value('id');

        $this->deleteJson("/api/v1/carts/{$cart->token}/items/{$itemId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.totals.total_cents', 0);
    }

    public function test_item_count_es_la_suma_de_cantidades_no_el_numero_de_lineas(): void
    {
        $a = $this->variantWithStock(onHand: 20);
        $b = $this->variantWithStock(onHand: 20);
        $cart = Cart::factory()->create();

        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $a->sku, 'quantity' => 2]);
        $this->postJson("/api/v1/carts/{$cart->token}/items", ['sku' => $b->sku, 'quantity' => 3]);

        $this->getJson("/api/v1/carts/{$cart->token}")
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.item_count', 5);
    }
}
