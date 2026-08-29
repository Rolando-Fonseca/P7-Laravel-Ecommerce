/**
 * Todo importe viaja en centavos enteros desde la API. El formateo es
 * responsabilidad de quien presenta, no de quien sirve: así el cliente nunca
 * hace aritmética de coma flotante sobre el precio.
 */
const cop = new Intl.NumberFormat("es-CO", {
  style: "currency",
  currency: "COP",
  maximumFractionDigits: 0,
});

export function price(cents: number): string {
  return cop.format(Math.round(cents / 100));
}

export function priceRange(min: number, max: number): string {
  return min === max ? price(min) : `${price(min)} – ${price(max)}`;
}
