<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/**
 * El libro mayor es la herramienta de auditoria del inventario. Si no se puede
 * consultar, el histórico append-only no sirve de nada.
 */
class InventoryMovementTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['admin']);

        return $user;
    }

    private function adjust(string $sku, int $delta, string $key): void
    {
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/admin/inventory/adjustments', [
                'sku' => $sku,
                'warehouse_code' => 'NGL-CEN',
                'quantity_delta' => $delta,
                'reason' => 'Movimiento de prueba para el libro mayor',
            ])->assertStatus(201);
    }

    public function test_el_libro_mayor_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/admin/inventory/movements')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_el_libro_mayor_sin_habilidad_admin_devuelve_403(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['lectura']);

        $this->getJson('/api/v1/admin/inventory/movements')->assertStatus(403);
    }

    public function test_el_libro_mayor_devuelve_la_estructura_paginada_del_contrato(): void
    {
        $this->admin();
        $variant = $this->variantWithStock(onHand: 0);
        $this->adjust($variant->sku, 10, 'MAYOR-1');

        $this->getJson('/api/v1/admin/inventory/movements')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['movement_id', 'sku', 'warehouse_code', 'type',
                    'quantity_delta', 'reason', 'reference', 'created_by', 'created_at']],
                'meta' => ['page', 'per_page', 'total', 'total_pages'],
                'links' => ['self', 'first', 'prev', 'next', 'last'],
            ])
            ->assertJsonPath('data.0.type', 'adjustment')
            ->assertJsonPath('data.0.quantity_delta', 10);
    }

    public function test_created_by_trae_el_usuario_en_un_ajuste_manual(): void
    {
        // null cuando lo genera el sistema, con usuario cuando fue un ajuste manual.
        $admin = $this->admin();
        $variant = $this->variantWithStock(onHand: 0);
        $this->adjust($variant->sku, 5, 'MAYOR-2');

        $this->getJson('/api/v1/admin/inventory/movements')
            ->assertOk()
            ->assertJsonPath('data.0.created_by.id', $admin->id);
    }

    public function test_una_reserva_por_pedido_queda_registrada_sin_usuario_y_con_referencia(): void
    {
        $variant = $this->variantWithStock(onHand: 10);
        $cart = $this->cartWith($variant, quantity: 2);

        $number = $this->withHeader('Idempotency-Key', 'MAYOR-PEDIDO')
            ->postJson('/api/v1/orders', $this->orderPayload($cart))
            ->assertStatus(201)
            ->json('data.number');

        $this->admin();

        $this->getJson('/api/v1/admin/inventory/movements?type=reservation')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.created_by', null)
            ->assertJsonPath('data.0.reference.type', 'Order')
            ->assertJsonPath('data.0.reference.id', $number);
    }

    public function test_los_filtros_del_libro_mayor_se_combinan(): void
    {
        $this->admin();
        $a = $this->variantWithStock(onHand: 0);
        $b = $this->variantWithStock(onHand: 0);

        $this->adjust($a->sku, 3, 'MAYOR-A');
        $this->adjust($b->sku, 7, 'MAYOR-B');

        $this->getJson("/api/v1/admin/inventory/movements?sku={$a->sku}&warehouse_code=NGL-CEN&type=adjustment")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity_delta', 3);
    }

    public function test_un_filtro_desconocido_en_el_libro_mayor_devuelve_422(): void
    {
        $this->admin();

        $this->getJson('/api/v1/admin/inventory/movements?tipo=adjustment')
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'tipo');
    }

    public function test_el_orden_ascendente_devuelve_primero_el_movimiento_mas_antiguo(): void
    {
        $this->admin();
        $variant = $this->variantWithStock(onHand: 0);

        $this->adjust($variant->sku, 3, 'ORDEN-1');
        $this->adjust($variant->sku, 9, 'ORDEN-2');

        $this->getJson('/api/v1/admin/inventory/movements?sort=created_at')
            ->assertOk()
            ->assertJsonPath('data.0.quantity_delta', 3);
    }
}
