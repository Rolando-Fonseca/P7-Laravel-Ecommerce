# Glosario — lenguaje ubicuo de Nogal

Estas palabras significan exactamente esto en el código, en los contratos y en las
conversaciones. Si una palabra de aquí aparece con otro sentido en algún sitio, es un bug
de documentación y hay que arreglarlo.

| Término | Significa | NO significa |
|---|---|---|
| **Producto** (`Product`) | La prenda como concepto comercial: "Camisa Oxford Manga Larga". Tiene nombre, descripción, material, categoría y precio base. **No tiene stock.** | Una unidad física |
| **Variante** (`ProductVariant`) | La combinación concreta y vendible: "Camisa Oxford / Azul cielo / M". Tiene SKU propio. **Es lo único que se puede añadir a un carrito.** | Un producto pequeño |
| **SKU** | Identificador comercial único de una variante. Formato `NGL-<TIPO>-<MODELO>-<COLOR>-<TALLA>`, ej. `NGL-CAM-OXF-AZC-M`. Estable, público, imprimible. | El id de base de datos |
| **Talla** (`size`) | Valor textual de la variante. Su interpretación depende de `size_system`. | Siempre S/M/L |
| **Sistema de tallas** (`size_system`) | `alpha` (S–XXL), `waist` (28–40, pantalón), `eu_shoe` (39–46), `unica` (accesorios) | — |
| **Almacén** (`Warehouse`) | Ubicación física donde hay existencias. El MVP arranca con uno solo (`NGL-CEN`), pero el modelo ya soporta varios. | Una tienda |
| **Existencia** (`InventoryItem`) | La fila que cruza una variante con un almacén y guarda cantidades. Es la única fuente de verdad del stock. | Un movimiento |
| **En mano** (`quantity_on_hand`) | Unidades físicamente presentes en el almacén. | Vendibles |
| **Reservado** (`quantity_reserved`) | Unidades comprometidas por pedidos aún no despachados. | Vendidas |
| **Disponible** (`available`) | `quantity_on_hand - quantity_reserved`. **Campo calculado, nunca columna.** Es lo que se muestra y lo que se valida al vender. | En mano |
| **Movimiento** (`InventoryMovement`) | Registro inmutable de un cambio de stock, con motivo y referencia. El histórico completo. | Un ajuste |
| **Carrito** (`Cart`) | Colección temporal de líneas, identificada por un token opaco. Puede no tener usuario. Caduca. | Un pedido sin pagar |
| **Línea de carrito** (`CartItem`) | Variante + cantidad + precio congelado en el momento de añadirla. | Una variante |
| **Pedido** (`Order`) | Compromiso de compra. Tiene número público, estado, totales congelados y dirección. **Inmutable en su contenido** una vez creado. | Un carrito confirmado editable |
| **Línea de pedido** (`OrderItem`) | Copia histórica de la línea de carrito. Guarda nombre y SKU en texto: si mañana renombran el producto, el pedido viejo no cambia. | Un puntero a la variante |
| **Número de pedido** | Identificador público `NGL-2026-000123`. Es lo que se usa en la URL y en los correos. | El id |
| **Clave de idempotencia** | Valor que envía el cliente para que repetir la misma petición no cree dos pedidos. | Un token de sesión |
| **traceId** | ULID único por petición. Aparece en el log y en toda respuesta de error. Es lo que pide un cliente cuando reporta un fallo. | El id de usuario |

## Sobre los precios

Todo importe es **entero en centavos** (`unsignedBigInteger`, sufijo `_cents`), moneda
en ISO-4217 (`COP` por defecto). Los `float` no representan `0.10` exactamente y en un
carrito de 12 líneas eso se convierte en un céntimo de diferencia que nadie sabe explicar.

`ProductVariant.price_cents` es **nullable**: si es `null`, la variante hereda
`Product.base_price_cents`. Así se soporta "la talla XXL cuesta 8.000 más" sin duplicar
el precio en cada fila.
