<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/**
 * Verifica la tabla "efecto de cada transicion sobre el stock" de
 * docs/domain/04-pedidos.md contra el comportamiento real.
 */
class OrderTransitionTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['admin']);
    }

    /** Crea un pedido de 2 unidades sobre un stock de 10 y devuelve su numero. */
    private function orderOf(int $onHand = 10, int $quantity = 2): string
    {
        $variant = $this->variantWithStock(onHand: $onHand);
        $cart = $this->cartWith($variant, quantity: $quantity);

        return $this->withHeader('Idempotency-Key', 'BASE-'.uniqid())
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(201)
            ->json('data.number');
    }

    private function transition(string $number, string $to, ?string $reason = null): TestResponse
    {
        return $this->withHeader('Idempotency-Key', 'TR-'.uniqid())
            ->postJson("/api/v1/admin/orders/{$number}/transitions", array_filter([
                'to' => $to,
                'reason' => $reason,
            ]));
    }

    public function test_pagar_no_mueve_inventario(): void
    {
        // El stock ya esta reservado desde que se creo el pedido y sigue fisicamente
        // en el almacen. Pagar no cambia eso.
        $number = $this->orderOf();
        $this->admin();

        $this->transition($number, 'paid')->assertOk()->assertJsonPath('data.status', 'paid');

        $item = InventoryItem::first();
        $this->assertSame(10, $item->quantity_on_hand);
        $this->assertSame(2, $item->quantity_reserved);
    }

    public function test_despachar_descuenta_el_stock_fisico_y_la_reserva(): void
    {
        // Aqui, y solo aqui, se descuenta on_hand. Confundir pagar con despachar es
        // el motivo mas frecuente de descuadres de inventario.
        $number = $this->orderOf();
        $this->admin();

        $this->transition($number, 'paid')->assertOk();
        $this->transition($number, 'packed')->assertOk();
        $this->transition($number, 'shipped')->assertOk()->assertJsonPath('data.status', 'shipped');

        $item = InventoryItem::first();
        $this->assertSame(8, $item->quantity_on_hand);
        $this->assertSame(0, $item->quantity_reserved);
        $this->assertSame(1, InventoryMovement::where('type', 'sale')->count());
    }

    public function test_cancelar_libera_la_reserva_sin_tocar_el_stock_fisico(): void
    {
        $number = $this->orderOf();
        $this->admin();

        $this->transition($number, 'cancelled', 'El cliente cambio de opinion')->assertOk();

        $item = InventoryItem::first();
        $this->assertSame(10, $item->quantity_on_hand);
        $this->assertSame(0, $item->quantity_reserved);
        $this->assertSame(1, InventoryMovement::where('type', 'release')->count());
    }

    public function test_una_devolucion_devuelve_las_unidades_al_stock(): void
    {
        $number = $this->orderOf();
        $this->admin();

        foreach (['paid', 'packed', 'shipped'] as $to) {
            $this->transition($number, $to)->assertOk();
        }

        $this->assertSame(8, InventoryItem::first()->quantity_on_hand);

        $this->transition($number, 'returned', 'Talla incorrecta')->assertOk();

        $this->assertSame(10, InventoryItem::first()->quantity_on_hand);
        $this->assertSame(1, InventoryMovement::where('type', 'return')->count());
    }

    public function test_un_pedido_enviado_no_se_puede_cancelar(): void
    {
        $number = $this->orderOf();
        $this->admin();

        foreach (['paid', 'packed', 'shipped'] as $to) {
            $this->transition($number, $to)->assertOk();
        }

        $response = $this->transition($number, 'cancelled', 'Intento fuera de tiempo')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');

        // El error incluye las transiciones legales: el cliente sabe que si puede
        // hacer sin mantener su propia copia de la maquina de estados.
        $response->assertJsonPath('error.details.0.meta.current_status', 'shipped')
            ->assertJsonPath('error.details.0.meta.allowed_transitions', ['returned']);
    }

    public function test_no_se_puede_saltar_de_creado_a_enviado(): void
    {
        $number = $this->orderOf();
        $this->admin();

        $this->transition($number, 'shipped')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_cancelar_sin_motivo_devuelve_422(): void
    {
        $number = $this->orderOf();
        $this->admin();

        $this->transition($number, 'cancelled')
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'reason');
    }

    public function test_la_transicion_sin_habilidad_admin_devuelve_403(): void
    {
        $number = $this->orderOf();
        Sanctum::actingAs(User::factory()->create(), ['lectura']);

        $this->transition($number, 'paid')->assertStatus(403);
    }

    public function test_el_historial_de_estados_se_acumula_en_orden(): void
    {
        $number = $this->orderOf();
        $this->admin();

        $this->transition($number, 'paid')->assertOk();
        $response = $this->transition($number, 'packed')->assertOk();

        $history = $response->json('data.status_history');

        $this->assertCount(3, $history);
        $this->assertSame(['created', 'paid', 'packed'], array_column($history, 'status'));
    }

    public function test_la_demo_completa_del_flujo_de_negocio(): void
    {
        // listar -> anadir al carrito -> crear pedido -> consultar pedido
        $variant = $this->variantWithStock(onHand: 10);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');

        $token = $this->postJson('/api/v1/carts')->assertStatus(201)->json('data.token');

        $this->postJson("/api/v1/carts/{$token}/items", ['sku' => $variant->sku, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.item_count', 2);

        $number = $this->withHeader('Idempotency-Key', 'DEMO-1')
            ->postJson('/api/v1/orders', [
                'cart_token' => $token,
                'email' => 'cliente@ejemplo.com',
                'shipping_address' => [
                    'full_name' => 'Andres Molina',
                    'phone' => '+57 300 123 4567',
                    'line1' => 'Carrera 45 # 26-30',
                    'city' => 'Medellin',
                    'state' => 'Antioquia',
                    'country' => 'CO',
                ],
            ])
            ->assertStatus(201)
            ->json('data.number');

        $this->getJson("/api/v1/orders/{$number}?email=cliente@ejemplo.com")
            ->assertOk()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertSame(2, ProductVariant::first()->inventoryItems->first()->quantity_reserved);
    }
}
