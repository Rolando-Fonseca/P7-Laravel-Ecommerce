import { isDarkSurface } from "@/lib/color";

/**
 * Ilustraciones de las prendas, dibujadas a mano en vector.
 *
 * No son fotos de banco: una foto de un modelo con una camisa cualquiera al
 * lado del SKU NGL-CAM-OXF-AZC-M sería una mentira pequeña pero mentira. Aquí
 * la prenda se rellena con el `color_hex` real de la variante, así que lo que
 * ves sigue saliendo de la base de datos.
 *
 * El trazo se aclara u oscurece según la luminancia del relleno, porque el
 * catálogo tiene "Blanco hueso" y "Negro" y un contorno fijo desaparece en uno
 * de los dos.
 */

export type GarmentKind =
  | "shirt"
  | "mandarin"
  | "tee"
  | "chino"
  | "jean"
  | "jacket"
  | "boot"
  | "belt";

export const garmentBySlug: Record<string, GarmentKind> = {
  "camisa-oxford-manga-larga": "shirt",
  "camisa-lino-cuello-mao": "mandarin",
  "camiseta-cuello-redondo-peso-pesado": "tee",
  "pantalon-chino-slim": "chino",
  "jean-recto-14oz": "jean",
  "chaqueta-trucker-mezclilla": "jacket",
  "bota-chelsea-cuero": "boot",
  "cinturon-cuero-hebilla-laton": "belt",
};

export function Garment({
  kind,
  color,
  className,
}: {
  kind: GarmentKind;
  color: string | null;
  className?: string;
}) {
  const fill = color ?? "#c9b79a";
  const oscuro = isDarkSurface(fill);
  const trazo = oscuro ? "rgba(247,243,237,0.55)" : "rgba(21,12,6,0.42)";
  const detalle = oscuro ? "rgba(247,243,237,0.38)" : "rgba(21,12,6,0.28)";

  const base = {
    fill,
    stroke: trazo,
    strokeWidth: 2.4,
    strokeLinejoin: "round" as const,
    strokeLinecap: "round" as const,
  };

  const linea = {
    fill: "none",
    stroke: detalle,
    strokeWidth: 1.8,
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
  };

  return (
    <svg
      viewBox="0 0 200 250"
      className={className}
      // Decorativa: el nombre de la prenda está en el encabezado de al lado,
      // así que anunciarla otra vez solo repite información.
      aria-hidden="true"
      focusable="false"
    >
      {kind === "shirt" && (
        <>
          <path
            {...base}
            d="M76 40 58 46 24 76 40 154 62 148 62 216 138 216 138 148 160 154 176 76 142 46 124 40 100 66Z"
          />
          <path {...linea} d="M76 40 100 66 86 76 68 52Z" />
          <path {...linea} d="M124 40 100 66 114 76 132 52Z" />
          <path {...linea} d="M100 66V216" />
          <path {...linea} d="M40 140 62 134M160 140 138 134" />
          <path {...linea} d="M72 96h26v26H72Z" />
          {[96, 124, 152, 180, 206].map((y) => (
            <circle key={y} cx="100" cy={y} r="2.6" fill={detalle} />
          ))}
        </>
      )}

      {kind === "mandarin" && (
        <>
          <path
            {...base}
            d="M78 48 58 54 24 84 40 160 62 154 62 216 138 216 138 154 160 160 176 84 142 54 122 48Z"
          />
          <path {...base} d="M84 30h32v20H84Z" strokeWidth="2.2" />
          <path {...linea} d="M100 50V216" />
          <path {...linea} d="M84 40h32" />
          <path {...linea} d="M40 146 62 140M160 146 138 140" />
          {[88, 118, 148, 178, 206].map((y) => (
            <circle key={y} cx="100" cy={y} r="2.6" fill={detalle} />
          ))}
        </>
      )}

      {kind === "tee" && (
        <>
          <path
            {...base}
            d="M74 44 48 54 32 102 58 114 60 216 140 216 142 114 168 102 152 54 126 44C118 60 82 60 74 44Z"
          />
          <path {...linea} d="M74 44C82 62 118 62 126 44" />
          <path {...linea} d="M58 114 60 128M142 114 140 128" />
        </>
      )}

      {kind === "chino" && (
        <>
          <path {...base} d="M60 38h80l8 178h-34l-14-96-14 96H52Z" />
          <path {...linea} d="M60 56h80" strokeWidth="3" />
          <path {...linea} d="M100 56v26" />
          <path {...linea} d="M64 62c8 12 18 16 26 14M136 62c-8 12-18 16-26 14" />
          <path {...linea} d="M70 92h20v8H70ZM110 92h20v8h-20Z" />
        </>
      )}

      {kind === "jean" && (
        <>
          <path {...base} d="M56 38h88l6 178h-36l-14-98-14 98H50Z" />
          <path {...linea} d="M56 58h88" strokeWidth="3" />
          <path {...linea} d="M100 58v28" />
          <path
            {...linea}
            d="M60 64c9 13 20 17 29 15M140 64c-9 13-20 17-29 15"
            strokeDasharray="4 3"
          />
          <path {...linea} d="M112 62h16v12h-16Z" />
          <path
            {...linea}
            d="M64 216 72 130M136 216 128 130"
            strokeDasharray="4 3"
          />
          {[
            [64, 78],
            [136, 78],
            [112, 62],
            [128, 62],
          ].map(([x, y]) => (
            <circle key={`${x}-${y}`} cx={x} cy={y} r="2.2" fill={detalle} />
          ))}
        </>
      )}

      {kind === "jacket" && (
        <>
          <path
            {...base}
            d="M72 42 48 48 18 78 36 148 60 142 60 176 140 176 140 142 164 148 182 78 152 48 128 42 100 68Z"
          />
          <path {...base} d="M60 176h80v22H60Z" strokeWidth="2.2" />
          <path {...linea} d="M72 42 100 68 82 84 56 52Z" />
          <path {...linea} d="M128 42 100 68 118 84 144 52Z" />
          <path {...linea} d="M100 68v108" />
          <path {...linea} d="M68 104h24v22H68ZM108 104h24v22h-24Z" />
          <path {...linea} d="M66 100h28M106 100h28" strokeWidth="3.2" />
          <path {...linea} d="M36 138 60 132M164 138 140 132" />
          {[92, 118, 144, 168].map((y) => (
            <circle key={y} cx="100" cy={y} r="2.6" fill={detalle} />
          ))}
        </>
      )}

      {kind === "boot" && (
        <>
          <path
            {...base}
            d="M66 48h62v72c24 6 42 22 48 42v12H66Z"
          />
          <path {...base} d="M58 174h126v12H58Z" strokeWidth="2.2" />
          <path {...base} d="M58 174h24v20H58Z" strokeWidth="2.2" />
          <path {...linea} d="M78 66h22v52H78Z" />
          <path
            {...linea}
            d="M84 70v44M90 70v44M96 70v44"
            strokeWidth="1.3"
          />
          <path {...linea} d="M128 48v72" />
          <path {...linea} d="M130 40h14v14h-14Z" />
        </>
      )}

      {kind === "belt" && (
        <>
          <circle
            cx="100"
            cy="140"
            r="52"
            fill="none"
            stroke={fill}
            strokeWidth="30"
          />
          <circle
            cx="100"
            cy="140"
            r="67"
            fill="none"
            stroke={trazo}
            strokeWidth="2.4"
          />
          <circle
            cx="100"
            cy="140"
            r="37"
            fill="none"
            stroke={trazo}
            strokeWidth="2.4"
          />
          <path {...base} d="M82 34h36v30H82Z" strokeWidth="2.4" />
          <path {...linea} d="M100 34v30M82 49h36" />
          {[104, 126, 148, 170].map((y) => (
            <circle key={y} cx="167" cy={y} r="2.4" fill={detalle} />
          ))}
        </>
      )}
    </svg>
  );
}
