<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['admin']);

        return $user;
    }

    public function test_la_consulta_publica_no_expone_on_hand_ni_reserved(): void
    {
        // Esas dos cantidades revelan volumen de ventas en curso. Un competidor que
        // consulta reserved cada hora sabe exactamente cuanto vendes.
        $variant = $this->variantWithStock(onHand: 10, reserved: 3);

        $data = $this->getJson("/api/v1/inventory/{$variant->sku}")->assertOk()->json('data');

        $this->assertSame(7, $data['available']);
        $this->assertArrayNotHasKey('quantity_on_hand', $data);
        $this->assertArrayNotHasKey('quantity_reserved', $data);
    }

    public function test_la_consulta_en_lote_devuelve_null_para_un_sku_inexistente(): void
    {
        // La peticion en lote es valida aunque un elemento no lo sea: devolver 404
        // por un sku obsoleto en cache romperia la pagina entera.
        $variant = $this->variantWithStock(onHand: 4);

        $this->postJson('/api/v1/inventory/availability', [
            'skus' => [$variant->sku, 'NGL-NO-EXISTE-M'],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.available', 4)
            ->assertJsonPath('data.1.available', null)
            ->assertJsonPath('data.1.in_stock', false);
    }

    public function test_el_ajuste_sin_token_devuelve_401(): void
    {
        $this->postJson('/api/v1/admin/inventory/adjustments', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_el_ajuste_con_token_sin_habilidad_admin_devuelve_403(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*/no-admin']);

        $this->postJson('/api/v1/admin/inventory/adjustments', [])->assertStatus(403);
    }

    public function test_el_ajuste_sin_idempotency_key_devuelve_400(): void
    {
        $this->admin();
        $variant = $this->variantWithStock();

        $this->postJson('/api/v1/admin/inventory/adjustments', [
            'sku' => $variant->sku,
            'warehouse_code' => 'NGL-CEN',
            'quantity_delta' => 5,
            'reason' => 'Recepcion de mercancia',
        ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_el_ajuste_genera_exactamente_un_movimiento_y_actualiza_el_stock(): void
    {
        $this->admin();
        $variant = $this->variantWithStock(onHand: 7, reserved: 2);

        $this->withHeader('Idempotency-Key', '01JBQ8M3P5T7W9X2Y4Z6A8B0C2')
            ->postJson('/api/v1/admin/inventory/adjustments', [
                'sku' => $variant->sku,
                'warehouse_code' => 'NGL-CEN',
                'quantity_delta' => 24,
                'reason' => 'Recepcion orden de compra OC-2026-0142',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.resulting_stock.quantity_on_hand', 31)
            ->assertJsonPath('data.resulting_stock.quantity_reserved', 2)
            ->assertJsonPath('data.resulting_stock.available', 29);

        $this->assertSame(1, InventoryMovement::where('type', 'adjustment')->count());
    }

    public function test_repetir_el_ajuste_con_la_misma_clave_no_duplica_el_movimiento(): void
    {
        $this->admin();
        $variant = $this->variantWithStock(onHand: 0);

        $payload = [
            'sku' => $variant->sku,
            'warehouse_code' => 'NGL-CEN',
            'quantity_delta' => 10,
            'reason' => 'Recepcion de mercancia',
        ];

        $first = $this->withHeader('Idempotency-Key', 'REPETIDA-1')
            ->postJson('/api/v1/admin/inventory/adjustments', $payload)->assertStatus(201);

        $second = $this->withHeader('Idempotency-Key', 'REPETIDA-1')
            ->postJson('/api/v1/admin/inventory/adjustments', $payload)->assertStatus(201);

        $second->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->json('data.movement_id'), $second->json('data.movement_id'));
        $this->assertSame(1, InventoryMovement::where('type', 'adjustment')->count());
        $this->assertSame(10, InventoryItem::first()->quantity_on_hand);
    }

    public function test_la_misma_clave_con_cuerpo_distinto_devuelve_409(): void
    {
        $this->admin();
        $variant = $this->variantWithStock(onHand: 0);

        $base = [
            'sku' => $variant->sku,
            'warehouse_code' => 'NGL-CEN',
            'reason' => 'Recepcion de mercancia',
        ];

        $this->withHeader('Idempotency-Key', 'CHOQUE-1')
            ->postJson('/api/v1/admin/inventory/adjustments', $base + ['quantity_delta' => 10])
            ->assertStatus(201);

        $this->withHeader('Idempotency-Key', 'CHOQUE-1')
            ->postJson('/api/v1/admin/inventory/adjustments', $base + ['quantity_delta' => 99])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
    }

    public function test_un_delta_negativo_que_dejaria_menos_stock_que_reservas_devuelve_409(): void
    {
        // Si hay 3 unidades comprometidas por pedidos en curso, no se puede bajar el
        // stock a 1: esos pedidos ya se reservaron.
        $this->admin();
        $variant = $this->variantWithStock(onHand: 5, reserved: 3);

        $this->withHeader('Idempotency-Key', 'NEGATIVO-1')
            ->postJson('/api/v1/admin/inventory/adjustments', [
                'sku' => $variant->sku,
                'warehouse_code' => 'NGL-CEN',
                'quantity_delta' => -4,
                'reason' => 'Merma por dano en bodega',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');

        $this->assertSame(5, InventoryItem::first()->quantity_on_hand);
    }

    public function test_un_ajuste_de_cero_unidades_devuelve_422(): void
    {
        $this->admin();
        $variant = $this->variantWithStock();

        $this->withHeader('Idempotency-Key', 'CERO-1')
            ->postJson('/api/v1/admin/inventory/adjustments', [
                'sku' => $variant->sku,
                'warehouse_code' => 'NGL-CEN',
                'quantity_delta' => 0,
                'reason' => 'Prueba de ajuste vacio',
            ])
            ->assertStatus(422);
    }

    public function test_un_ajuste_sin_motivo_devuelve_422(): void
    {
        // Un ajuste sin motivo es un descuadre que nadie puede auditar despues.
        $this->admin();
        $variant = $this->variantWithStock();

        $this->withHeader('Idempotency-Key', 'SIN-MOTIVO-1')
            ->postJson('/api/v1/admin/inventory/adjustments', [
                'sku' => $variant->sku,
                'warehouse_code' => 'NGL-CEN',
                'quantity_delta' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'reason');
    }

    public function test_la_suma_de_los_movimientos_coincide_con_el_stock_fisico(): void
    {
        // Invariante del libro mayor. Si no se cumple, hay un UPDATE en el codigo
        // que se salto inventory_movements.
        $this->admin();
        $variant = $this->variantWithStock(onHand: 0);

        foreach ([10, 5, -3] as $i => $delta) {
            $this->withHeader('Idempotency-Key', "LIBRO-{$i}")
                ->postJson('/api/v1/admin/inventory/adjustments', [
                    'sku' => $variant->sku,
                    'warehouse_code' => 'NGL-CEN',
                    'quantity_delta' => $delta,
                    'reason' => 'Movimiento de prueba numero '.$i,
                ])->assertStatus(201);
        }

        $item = InventoryItem::first();
        $sum = InventoryMovement::where('inventory_item_id', $item->id)
            ->whereIn('type', ['adjustment', 'sale', 'return'])
            ->sum('quantity_delta');

        $this->assertSame(12, $item->quantity_on_hand);
        $this->assertSame(12, (int) $sum);
    }
}
