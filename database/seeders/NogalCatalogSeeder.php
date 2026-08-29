<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalogo de arranque de Nogal, ropa masculina.
 *
 * Los precios estan en centavos de peso colombiano: 1890000 son 18.900 COP.
 */
class NogalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'NGL-CEN'],
            ['name' => 'Bodega Central', 'city' => 'Medellin', 'is_default' => true, 'is_active' => true]
        );

        foreach ($this->catalog() as $position => $group) {
            $category = Category::firstOrCreate(
                ['slug' => $group['slug']],
                [
                    'name' => $group['name'],
                    'size_system' => $group['size_system'],
                    'position' => $position + 1,
                ]
            );

            foreach ($group['products'] as $data) {
                $product = Product::firstOrCreate(
                    ['slug' => $data['slug']],
                    [
                        'category_id' => $category->id,
                        'name' => $data['name'],
                        'summary' => $data['summary'],
                        'description' => $data['description'],
                        'material' => $data['material'],
                        'care_instructions' => $data['care'],
                        'base_price_cents' => $data['price'],
                        'currency' => 'COP',
                        'status' => 'active',
                    ]
                );

                ProductImage::firstOrCreate(
                    ['product_id' => $product->id, 'position' => 1],
                    [
                        'url' => "https://cdn.nogal.store/p/{$data['slug']}-1.webp",
                        'alt' => $data['name'].', vista frontal sobre fondo neutro',
                        'is_primary' => true,
                    ]
                );

                foreach ($data['colors'] as $color) {
                    foreach ($group['sizes'] as $size) {
                        $sku = $this->sku($data['code'], $color['code'], $size);

                        $variant = ProductVariant::firstOrCreate(
                            ['sku' => $sku],
                            [
                                'product_id' => $product->id,
                                'size' => $size,
                                'size_system' => $group['size_system'],
                                'color_name' => $color['name'],
                                'color_hex' => $color['hex'],
                                // La XXL cuesta 2.000 mas. Es el caso que justifica
                                // que price_cents sea nullable en la variante.
                                'price_cents' => $size === 'XXL' ? $data['price'] + 200000 : null,
                                'barcode' => '770'.random_int(1000000000, 9999999999),
                                'weight_grams' => $data['weight'],
                                'status' => 'active',
                            ]
                        );

                        $onHand = random_int(0, 14);

                        $item = InventoryItem::firstOrCreate(
                            ['product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id],
                            ['quantity_on_hand' => $onHand, 'quantity_reserved' => 0, 'reorder_point' => 3]
                        );

                        // Toda existencia nace con su movimiento. El libro mayor no
                        // tiene excepciones ni siquiera en la carga inicial.
                        if ($item->wasRecentlyCreated && $onHand > 0) {
                            InventoryMovement::create([
                                'inventory_item_id' => $item->id,
                                'type' => MovementType::Adjustment,
                                'quantity_delta' => $onHand,
                                'reason' => 'Carga inicial de inventario',
                            ]);
                        }
                    }
                }
            }
        }
    }

    private function sku(string $product, string $color, string $size): string
    {
        return Str::upper("NGL-{$product}-{$color}-{$size}");
    }

    /** @return array<int, array<string, mixed>> */
    private function catalog(): array
    {
        return [
            [
                'slug' => 'camisas',
                'name' => 'Camisas',
                'size_system' => 'alpha',
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'products' => [
                    [
                        'code' => 'CAM-OXF',
                        'slug' => 'camisa-oxford-manga-larga',
                        'name' => 'Camisa Oxford Manga Larga',
                        'summary' => 'Algodon peinado, cuello con botones, corte regular.',
                        'description' => 'Tejido Oxford de algodon peinado de 140 g/m2. Cuello con botones, canesu posterior con pliegue central y punos ajustables de dos posiciones. Se plancha facil y aguanta lavados.',
                        'material' => '100% algodon peinado',
                        'care' => 'Lavar a maquina en frio. No usar blanqueador. Planchar a temperatura media.',
                        'price' => 1890000,
                        'weight' => 280,
                        'colors' => [
                            ['code' => 'AZC', 'name' => 'Azul cielo', 'hex' => '#A8C5DA'],
                            ['code' => 'BLH', 'name' => 'Blanco hueso', 'hex' => '#F2EFE9'],
                            ['code' => 'RAY', 'name' => 'Rayado azul', 'hex' => '#7B9BB5'],
                        ],
                    ],
                    [
                        'code' => 'CAM-LIN',
                        'slug' => 'camisa-lino-cuello-mao',
                        'name' => 'Camisa de Lino Cuello Mao',
                        'summary' => 'Lino lavado, sin cuello clasico, para clima calido.',
                        'description' => 'Lino europeo lavado a la piedra. Cuello mao, sin entretela, caida suelta. Se arruga: es lino y asi debe verse.',
                        'material' => '100% lino',
                        'care' => 'Lavar a mano o ciclo delicado. Secar a la sombra.',
                        'price' => 2340000,
                        'weight' => 240,
                        'colors' => [
                            ['code' => 'ARE', 'name' => 'Arena', 'hex' => '#D6C7AE'],
                            ['code' => 'VOL', 'name' => 'Verde oliva', 'hex' => '#6B7355'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'camisetas',
                'name' => 'Camisetas',
                'size_system' => 'alpha',
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'products' => [
                    [
                        'code' => 'CMT-PES',
                        'slug' => 'camiseta-cuello-redondo-peso-pesado',
                        'name' => 'Camiseta Cuello Redondo Peso Pesado',
                        'summary' => 'Jersey de 240 g/m2. No se transparenta ni se deforma.',
                        'description' => 'Jersey de algodon de 240 g/m2 con cuello reforzado en costilla y costuras laterales. Encoge menos de un 3% en el primer lavado.',
                        'material' => '100% algodon',
                        'care' => 'Lavar del reves en frio. No secar en secadora.',
                        'price' => 890000,
                        'weight' => 220,
                        'colors' => [
                            ['code' => 'NEG', 'name' => 'Negro', 'hex' => '#1C1C1C'],
                            ['code' => 'BLC', 'name' => 'Blanco', 'hex' => '#FAFAFA'],
                            ['code' => 'GRJ', 'name' => 'Gris jaspeado', 'hex' => '#9A9A93'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'pantalones',
                'name' => 'Pantalones',
                'size_system' => 'waist',
                'sizes' => ['30', '32', '34', '36', '38'],
                'products' => [
                    [
                        'code' => 'PAN-CHI',
                        'slug' => 'pantalon-chino-slim',
                        'name' => 'Pantalon Chino Slim',
                        'summary' => 'Sarga de algodon con elastano. Corte ajustado sin apretar.',
                        'description' => 'Sarga de algodon con 2% de elastano. Corte slim con entrepierna de 78 cm, dos bolsillos frontales inclinados y dos traseros con boton.',
                        'material' => '98% algodon, 2% elastano',
                        'care' => 'Lavar a maquina en frio. Planchar del reves.',
                        'price' => 2150000,
                        'weight' => 480,
                        'colors' => [
                            ['code' => 'BEI', 'name' => 'Beige arena', 'hex' => '#C8B89A'],
                            ['code' => 'AZM', 'name' => 'Azul marino', 'hex' => '#2C3E50'],
                        ],
                    ],
                    [
                        'code' => 'PAN-JEA',
                        'slug' => 'jean-recto-14oz',
                        'name' => 'Jean Recto 14 oz',
                        'summary' => 'Mezclilla rigida de 14 onzas. Se amolda con el uso.',
                        'description' => 'Mezclilla rigida de 14 onzas tejida en telar de proyectil. Corte recto desde la rodilla, remaches de cobre y cinturilla reforzada. Los primeros usos son duros; a las tres semanas es otro pantalon.',
                        'material' => '100% algodon',
                        'care' => 'Primer lavado a mano en frio. Lavar poco.',
                        'price' => 2890000,
                        'weight' => 720,
                        'colors' => [
                            ['code' => 'IND', 'name' => 'Indigo crudo', 'hex' => '#2B3A55'],
                            ['code' => 'NEL', 'name' => 'Negro lavado', 'hex' => '#3A3A3A'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'chaquetas',
                'name' => 'Chaquetas',
                'size_system' => 'alpha',
                'sizes' => ['S', 'M', 'L', 'XL'],
                'products' => [
                    [
                        'code' => 'CHQ-TRK',
                        'slug' => 'chaqueta-trucker-mezclilla',
                        'name' => 'Chaqueta Trucker de Mezclilla',
                        'summary' => 'Mezclilla de 12 onzas, corte corto, seis botones.',
                        'description' => 'Mezclilla de 12 onzas. Corte corte clasico trucker con canesu en V, dos bolsillos de pecho con solapa y ajuste lateral por botones.',
                        'material' => '100% algodon',
                        'care' => 'Lavar poco y en frio.',
                        'price' => 3450000,
                        'weight' => 850,
                        'colors' => [
                            ['code' => 'IND', 'name' => 'Indigo medio', 'hex' => '#4A6491'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'calzado',
                'name' => 'Calzado',
                'size_system' => 'eu_shoe',
                'sizes' => ['40', '41', '42', '43', '44'],
                'products' => [
                    [
                        'code' => 'CAL-CHE',
                        'slug' => 'bota-chelsea-cuero',
                        'name' => 'Bota Chelsea de Cuero',
                        'summary' => 'Cuero vacuno de flor entera, suela de goma cosida.',
                        'description' => 'Cuero vacuno de flor entera curtido al vegetal, elasticos laterales y tirador trasero. Suela de goma cosida en Blake, resuelable.',
                        'material' => 'Cuero vacuno flor entera',
                        'care' => 'Cepillar y aplicar crema nutritiva cada tres meses.',
                        'price' => 5890000,
                        'weight' => 1200,
                        'colors' => [
                            ['code' => 'MAR', 'name' => 'Marron cuero', 'hex' => '#6B4423'],
                            ['code' => 'NEG', 'name' => 'Negro', 'hex' => '#1C1C1C'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'accesorios',
                'name' => 'Accesorios',
                'size_system' => 'unica',
                'sizes' => ['U'],
                'products' => [
                    [
                        'code' => 'ACC-CIN',
                        'slug' => 'cinturon-cuero-hebilla-laton',
                        'name' => 'Cinturon de Cuero con Hebilla de Laton',
                        'summary' => 'Una sola pieza de cuero, 3,5 cm de ancho.',
                        'description' => 'Cortado de una sola pieza de cuero vacuno de 3,5 mm. Hebilla de laton macizo intercambiable. Se oscurece con el uso.',
                        'material' => 'Cuero vacuno y laton',
                        'care' => 'Limpiar con pano humedo. No sumergir.',
                        'price' => 1450000,
                        'weight' => 260,
                        'colors' => [
                            ['code' => 'MAR', 'name' => 'Marron', 'hex' => '#6B4423'],
                            ['code' => 'NEG', 'name' => 'Negro', 'hex' => '#1C1C1C'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
