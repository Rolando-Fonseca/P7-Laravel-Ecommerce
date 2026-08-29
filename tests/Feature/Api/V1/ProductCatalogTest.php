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

    public function test_el_detalle_de_variante_devuelve_el_stock_por_almacen(): void
    {
        $variant = $this->variantWithStock(onHand: 9, reserved: 2, variantAttributes: [
            'sku' => 'NGL-CAM-OXF-AZC-M',
            'size' => 'M',
            'color_name' => 'Azul cielo',
        ]);

        $this->getJson("/api/v1/products/{$variant->product->slug}/variants/NGL-CAM-OXF-AZC-M")
            ->assertOk()
            ->assertJsonPath('data.sku', 'NGL-CAM-OXF-AZC-M')
            ->assertJsonPath('data.color.name', 'Azul cielo')
            ->assertJsonPath('data.stock.available', 7)
            ->assertJsonPath('data.stock.by_warehouse.0.warehouse_code', 'NGL-CEN');
    }

    public function test_una_variante_que_no_pertenece_a_ese_producto_devuelve_404(): void
    {
        $uno = $this->variantWithStock(variantAttributes: ['sku' => 'NGL-AAA-BBB-CCC-M']);
        $otro = $this->variantWithStock(variantAttributes: ['sku' => 'NGL-XXX-YYY-ZZZ-L']);

        $this->getJson("/api/v1/products/{$uno->product->slug}/variants/NGL-XXX-YYY-ZZZ-L")
            ->assertStatus(404);
    }

    public function test_el_filtro_por_talla_acepta_varios_valores_separados_por_coma(): void
    {
        $this->variantWithStock(variantAttributes: ['size' => 'M']);
        $this->variantWithStock(variantAttributes: ['size' => 'XXL']);

        $this->getJson('/api/v1/products?size=M')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?size=M,XXL')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/products?size=XS')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_filtro_por_color_ignora_mayusculas(): void
    {
        $this->variantWithStock(variantAttributes: ['color_name' => 'Azul cielo']);
        $this->variantWithStock(variantAttributes: ['color_name' => 'Verde oliva']);

        $this->getJson('/api/v1/products?color=AZUL')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?color=azul,verde')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_la_busqueda_encuentra_por_nombre_y_por_descripcion(): void
    {
        $this->variantWithStock(productAttributes: [
            'name' => 'Camisa Oxford Manga Larga',
            'description' => 'Tejido de algodon peinado.',
        ]);
        $this->variantWithStock(productAttributes: [
            'name' => 'Bota Chelsea',
            'description' => 'Cuero curtido al vegetal con suela cosida.',
        ]);

        $this->getJson('/api/v1/products?q=oxford')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?q=vegetal')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?q=nailon')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_una_busqueda_de_un_solo_caracter_devuelve_422(): void
    {
        // Recorre la tabla entera para devolver casi todo. ADR-0009.
        $this->getJson('/api/v1/products?q=a')->assertStatus(422);
    }

    public function test_el_orden_por_precio_ascendente_y_descendente(): void
    {
        $this->variantWithStock(productAttributes: ['name' => 'Barato', 'base_price_cents' => 500_000]);
        $this->variantWithStock(productAttributes: ['name' => 'Caro', 'base_price_cents' => 9_000_000]);

        $this->getJson('/api/v1/products?sort=price')
            ->assertOk()->assertJsonPath('data.0.name', 'Barato');

        $this->getJson('/api/v1/products?sort=-price')
            ->assertOk()->assertJsonPath('data.0.name', 'Caro');
    }
}
