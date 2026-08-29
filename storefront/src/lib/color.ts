/**
 * Los fondos de los swatches son los `color_hex` reales de las variantes, y el
 * catálogo incluye tanto "Blanco hueso" como "Negro". Un color de texto fijo
 * funciona en uno y es ilegible en el otro.
 *
 * Se decide por luminancia relativa (la misma fórmula que usa WCAG para el
 * contraste), no por "parece oscuro".
 */
export function isDarkSurface(hex: string | null | undefined): boolean {
  if (!hex) return false;

  const raw = hex.replace("#", "").trim();
  const full =
    raw.length === 3
      ? raw
          .split("")
          .map((c) => c + c)
          .join("")
      : raw;

  if (full.length !== 6) return false;

  const [r, g, b] = [0, 2, 4]
    .map((i) => parseInt(full.slice(i, i + 2), 16) / 255)
    .map((v) => (v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)));

  const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

  // 0.35 deja "Azul cielo" y "Beige arena" como claros, y manda "Negro" e
  // "Indigo crudo" al lado oscuro.
  return luminance < 0.35;
}

/** Par de clases de texto para escribir encima de un color arbitrario. */
export function inkOn(hex: string | null | undefined) {
  return isDarkSurface(hex)
    ? { strong: "text-background/85", soft: "text-background/60" }
    : { strong: "text-ink/80", soft: "text-ink/55" };
}
