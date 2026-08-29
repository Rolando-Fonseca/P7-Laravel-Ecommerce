<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    public function test_el_listado_devuelve_la_estructura_completa_del_contrato(): void
    {
        $this->variantWithStock();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()->assertJsonStructure([
            'data' => [['slug', 'name', 'category' => ['slug', 'name'], 'base_price_cents',
                'currency', 'price_range_cents' => ['min', 'max'], 'available_sizes',
                'available_colors', 'in_stock', 'variant_count', 'created_at']],
            'meta' => ['page', 'per_page', 'total', 'total_pages'],
            'links' => ['self', 'first', 'prev', 'next', 'last'],
        ]);
    }

    public function test_prev_y_next_son_null_en_los_extremos_pero_nunca_se_omiten(): void
    {
        $this->variantWithStock();

        $json = $this->getJson('/api/v1/products')->json();

        $this->assertArrayHasKey('prev', $json['links']);
        $this->assertArrayHasKey('next', $json['links']);
        $this->assertNull($json['links']['prev']);
        $this->assertNull($json['links']['next']);
    }

    public function test_per_page_fuera_de_rango_devuelve_422_y_no_se_recorta_en_silencio(): void
    {
        $this->getJson('/api/v1/products?per_page=999')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_un_parametro_desconocido_devuelve_422_y_no_se_ignora(): void
    {
        // Quien escribe ?categoria= en vez de ?category= debe enterarse. Una API
        // permisiva le devuelve el catalogo entero y el cliente cree que filtro.
        $this->getJson('/api/v1/products?categoria=camisas')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.0.field', 'categoria');
    }

    public function test_un_campo_de_orden_no_permitido_devuelve_422(): void
    {
        $this->getJson('/api/v1/products?sort=base_price_cents')->assertStatus(422);
    }

    public function test_los_productos_borrador_y_archivados_no_aparecen_en_el_listado(): void
    {
        $this->variantWithStock(productAttributes: ['status' => 'active']);
        $this->variantWithStock(productAttributes: ['status' => 'draft']);
        $this->variantWithStock(productAttributes: ['status' => 'archived']);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_producto_sin_variantes_activas_no_aparece_en_el_listado(): void
    {
        $this->variantWithStock(variantAttributes: ['status' => 'archived']);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_filtro_de_precio_considera_el_precio_propio_de_la_variante(): void
    {
        // El filtro que se rompe siempre: consultar solo products.base_price_cents
        // ignora las variantes con precio propio y devuelve resultados incorrectos.
        $this->variantWithStock(
            variantAttributes: ['price_cents' => 5_000_000],
            productAttributes: ['base_price_cents' => 1_000_000],
        );

        $this->getJson('/api/v1/products?price_min=4000000')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/products?price_max=2000000')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_el_filtro_in_stock_excluye_los_productos_agotados(): void
    {
        $this->variantWithStock(onHand: 5);
        $this->variantWithStock(onHand: 0);

        $this->getJson('/api/v1/products?in_stock=true')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?in_stock=false')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_detalle_devuelve_el_precio_de_la_variante_ya_resuelto(): void
    {
        $variant = $this->variantWithStock(
            variantAttributes: ['price_cents' => null],
            productAttributes: ['base_price_cents' => 1_890_000],
        );

        $this->getJson("/api/v1/products/{$variant->product->slug}")
            ->assertOk()
            ->assertJsonPath('data.variants.0.price_cents', 1_890_000);
    }

    public function test_el_detalle_de_un_producto_archivado_devuelve_404_y_no_410(): void
    {
        // Un 410 confirmaria que existio, y eso filtra informacion comercial:
        // que se dejo de vender y cuando.
        $variant = $this->variantWithStock(productAttributes: ['status' => 'archived']);

        $this->getJson("/api/v1/products/{$variant->product->slug}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_toda_respuesta_de_error_lleva_traceid_y_details_como_array(): void
    {
        $json = $this->getJson('/api/v1/products/no-existe')->assertStatus(404)->json();

        $this->assertIsArray($json['error']['details']);
        $this->assertNotEmpty($json['error']['traceId']);
    }

    public function test_toda_respuesta_lleva_el_header_x_trace_id(): void
    {
        $this->getJson('/api/v1/categories')->assertOk()->assertHeader('X-Trace-Id');
    }

    public function test_las_categorias_cuentan_solo_productos_activos(): void
    {
        $variant = $this->variantWithStock();
        $categorySlug = $variant->product->category->slug;

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $categorySlug)
            ->assertJsonPath('data.0.product_count', 1);
    }
}
