<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Cart;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    public function test_crear_pedido_sin_idempotency_key_devuelve_400(): void
    {
        $variant = $this->variantWithStock();
        $cart = $this->cartWith($variant);

        $this->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_el_camino_feliz_crea_el_pedido_reserva_stock_y_convierte_el_carrito(): void
    {
        $variant = $this->variantWithStock(onHand: 10);
        $cart = $this->cartWith($variant, quantity: 2);

        $response = $this->withHeader('Idempotency-Key', 'FELIZ-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(201);

        $response->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.allowed_transitions', ['paid', 'cancelled'])
            ->assertHeader('Location');

        $this->assertMatchesRegularExpression(
            '/^NGL-\d{4}-\d{6}$/',
            $response->json('data.number')
        );

        $item = InventoryItem::first();
        $this->assertSame(10, $item->quantity_on_hand, 'El stock fisico no se toca al crear el pedido.');
        $this->assertSame(2, $item->quantity_reserved);

        $this->assertSame('converted', Cart::find($cart->id)->status->value);
    }

    public function test_las_lineas_del_pedido_copian_el_nombre_del_producto_en_texto(): void
    {
        // Un pedido que muestra el nombre ACTUAL del producto es un pedido que
        // miente sobre el pasado.
        $variant = $this->variantWithStock(onHand: 5, productAttributes: ['name' => 'Camisa Oxford Original']);
        $cart = $this->cartWith($variant);

        $number = $this->withHeader('Idempotency-Key', 'TEXTO-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(201)
            ->json('data.number');

        $variant->product->update(['name' => 'Nombre Cambiado En 2027']);

        $this->getJson("/api/v1/orders/{$number}?email=cliente@ejemplo.com")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Camisa Oxford Original');
    }

    public function test_repetir_con_la_misma_clave_devuelve_el_mismo_pedido_y_no_reserva_dos_veces(): void
    {
        $variant = $this->variantWithStock(onHand: 10);
        $cart = $this->cartWith($variant, quantity: 3);
        $payload = $this->orderPayload($cart);

        $first = $this->withHeader('Idempotency-Key', 'DOBLE-CLIC')
            ->postJson('/api/v1/orders', $payload)->assertStatus(201);

        $second = $this->withHeader('Idempotency-Key', 'DOBLE-CLIC')
            ->postJson('/api/v1/orders', $payload)->assertStatus(201);

        $second->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->json('data.number'), $second->json('data.number'));
        $this->assertSame(1, Order::count());
        $this->assertSame(3, InventoryItem::first()->quantity_reserved);
    }

    public function test_la_misma_clave_con_cuerpo_distinto_devuelve_409(): void
    {
        $variant = $this->variantWithStock(onHand: 10);
        $cart = $this->cartWith($variant);
        $payload = $this->orderPayload($cart);

        $this->withHeader('Idempotency-Key', 'CHOQUE-PEDIDO')
            ->postJson('/api/v1/orders', $payload)->assertStatus(201);

        $payload['email'] = 'otro@ejemplo.com';

        $this->withHeader('Idempotency-Key', 'CHOQUE-PEDIDO')
            ->postJson('/api/v1/orders', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
    }

    public function test_un_carrito_vacio_devuelve_422(): void
    {
        $cart = Cart::factory()->create();

        $this->withHeader('Idempotency-Key', 'VACIO-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CART_EMPTY');
    }

    public function test_stock_insuficiente_devuelve_409_listando_todas_las_lineas_que_fallan(): void
    {
        // Si el usuario tiene dos problemas, tiene que verlos los dos a la vez.
        $a = $this->variantWithStock(onHand: 1);
        $b = $this->variantWithStock(onHand: 0);

        $cart = $this->cartWith($a, quantity: 3);
        $cart->items()->create([
            'product_variant_id' => $b->id,
            'quantity' => 2,
            'unit_price_cents' => $b->effectivePriceCents(),
        ]);

        $response = $this->withHeader('Idempotency-Key', 'SIN-STOCK-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');

        $this->assertCount(2, $response->json('error.details'));
        $this->assertSame(3, $response->json('error.details.0.meta.requested'));
        $this->assertSame(1, $response->json('error.details.0.meta.available'));
    }

    public function test_si_una_linea_falla_no_se_crea_el_pedido_ni_se_reserva_nada(): void
    {
        // Atomicidad. No hay pedidos parciales: esa decision es del cliente, no del
        // servidor.
        $ok = $this->variantWithStock(onHand: 50);
        $sinStock = $this->variantWithStock(onHand: 0);

        $cart = $this->cartWith($ok, quantity: 1);
        $cart->items()->create([
            'product_variant_id' => $sinStock->id,
            'quantity' => 1,
            'unit_price_cents' => $sinStock->effectivePriceCents(),
        ]);

        $this->withHeader('Idempotency-Key', 'ATOMICO-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(409);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, (int) InventoryItem::sum('quantity_reserved'));
        $this->assertSame('open', Cart::find($cart->id)->status->value);
    }

    public function test_un_fallo_no_consume_la_clave_de_idempotencia(): void
    {
        // El cliente debe poder corregir y reintentar con la misma clave.
        $variant = $this->variantWithStock(onHand: 0);
        $cart = $this->cartWith($variant, quantity: 1);

        $this->withHeader('Idempotency-Key', 'REINTENTO-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(409);

        InventoryItem::first()->update(['quantity_on_hand' => 5]);

        $this->withHeader('Idempotency-Key', 'REINTENTO-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(201);
    }

    public function test_el_total_es_la_suma_de_las_lineas(): void
    {
        $variant = $this->variantWithStock(onHand: 10, productAttributes: ['base_price_cents' => 1_890_000]);
        $cart = $this->cartWith($variant, quantity: 2);

        $this->withHeader('Idempotency-Key', 'TOTAL-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(201)
            ->assertJsonPath('data.totals.subtotal_cents', 3_780_000)
            ->assertJsonPath('data.totals.total_cents', 3_780_000)
            ->assertJsonPath('data.items.0.line_total_cents', 3_780_000);
    }

    public function test_consultar_con_un_email_que_no_coincide_devuelve_404_y_no_403(): void
    {
        // Un 403 confirma que el pedido existe y convierte el endpoint en un oraculo
        // para adivinar numeros de pedido.
        $variant = $this->variantWithStock(onHand: 5);
        $cart = $this->cartWith($variant);

        $number = $this->withHeader('Idempotency-Key', 'EMAIL-1')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->json('data.number');

        $this->getJson("/api/v1/orders/{$number}?email=intruso@ejemplo.com")->assertStatus(404);
        $this->getJson("/api/v1/orders/{$number}?email=cliente@ejemplo.com")->assertOk();
    }

    public function test_el_historial_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_el_historial_solo_devuelve_los_pedidos_del_usuario_autenticado(): void
    {
        $mio = User::factory()->create();
        $ajeno = User::factory()->create();

        Order::factory()->create(['user_id' => $mio->id]);
        Order::factory()->count(2)->create(['user_id' => $ajeno->id]);

        Sanctum::actingAs($mio);

        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data');
    }
}
