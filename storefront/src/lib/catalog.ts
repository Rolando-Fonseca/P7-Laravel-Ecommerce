import data from "@/data/catalog.json";

/**
 * El catálogo NO está escrito a mano. Lo genera `php artisan catalog:export`
 * desde la misma base de datos que sirve la API, usando los mismos accesores de
 * precio y stock. Si el contrato cambia, este JSON cambia con él.
 *
 * Vercel no ejecuta PHP, así que el escaparate no puede llamar a la API en
 * vivo. La alternativa era inventar datos en el frontend, que es peor: el
 * escaparate dejaría de demostrar nada sobre el backend.
 */

export type Color = { name: string; hex: string | null };

export type SampleVariant = {
  sku: string;
  size: string;
  color: Color;
  price_cents: number;
  available: number;
};

export type Product = {
  slug: string;
  name: string;
  summary: string | null;
  description: string | null;
  material: string | null;
  care_instructions: string | null;
  category: { slug: string; name: string };
  base_price_cents: number;
  price_range_cents: { min: number; max: number };
  available_sizes: string[];
  available_colors: Color[];
  in_stock: boolean;
  variant_count: number;
  total_available: number;
  sample_variant: SampleVariant | null;
};

export type Category = {
  slug: string;
  name: string;
  size_system: string;
  product_count: number;
};

export const categories = data.categories as Category[];
export const products = data.products as Product[];
export const currency = data.currency as string;

export const totals = {
  products: products.length,
  categories: categories.length,
  variants: products.reduce((sum, p) => sum + p.variant_count, 0),
  units: products.reduce((sum, p) => sum + p.total_available, 0),
};

/** El sistema de tallas cambia por categoría: una camisa usa S–XXL, un pantalón
 *  la cintura en pulgadas y un zapato la talla europea. */
export const sizeSystemLabel: Record<string, string> = {
  alpha: "Tallas S a XXL",
  waist: "Cintura en pulgadas",
  eu_shoe: "Talla europea",
  unica: "Talla única",
};
