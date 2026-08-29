<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * La tabla de transiciones de docs/domain/04-pedidos.md, verificada celda por celda.
 *
 * Este test es la red que impide que alguien "arregle" la maquina de estados
 * anadiendo una transicion comoda sin actualizar la documentacion.
 */
class OrderStatusTest extends TestCase
{
    public function test_las_transiciones_legales_son_exactamente_las_documentadas(): void
    {
        $expected = [
            'created' => ['paid', 'cancelled'],
            'paid' => ['packed', 'cancelled'],
            'packed' => ['shipped', 'cancelled'],
            'shipped' => ['returned'],
            'cancelled' => [],
            'returned' => [],
        ];

        foreach ($expected as $from => $targets) {
            $this->assertSame(
                $targets,
                OrderStatus::from($from)->allowedTransitionValues(),
                "Las transiciones desde {$from} no coinciden con la tabla del dominio."
            );
        }
    }

    public function test_un_pedido_enviado_no_se_puede_cancelar(): void
    {
        // Ya salio del almacen. Lo que existe es la devolucion, que es otro proceso
        // y con efecto contrario sobre el stock.
        $this->assertFalse(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled));
        $this->assertTrue(OrderStatus::Shipped->canTransitionTo(OrderStatus::Returned));
    }

    public function test_no_se_puede_saltar_de_creado_a_enviado(): void
    {
        $this->assertFalse(OrderStatus::Created->canTransitionTo(OrderStatus::Shipped));
    }

    public function test_cancelado_y_devuelto_son_terminales(): void
    {
        $this->assertTrue(OrderStatus::Cancelled->isTerminal());
        $this->assertTrue(OrderStatus::Returned->isTerminal());
        $this->assertFalse(OrderStatus::Created->isTerminal());
    }
}
