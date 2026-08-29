import type { Metadata } from "next";
import { Fraunces, Manrope } from "next/font/google";
import "./globals.css";

/* Los nombres de variable de la fuente tienen que ser DISTINTOS de las claves
   del tema (--font-heading / --font-body). Si coinciden, el mapeo de
   @theme inline se referencia a sí mismo y resuelve a nada. */
const fraunces = Fraunces({
  variable: "--font-fraunces",
  subsets: ["latin"],
  display: "swap",
});

const manrope = Manrope({
  variable: "--font-manrope",
  subsets: ["latin"],
  display: "swap",
});

export const metadata: Metadata = {
  metadataBase: new URL("https://nogal.store"),
  title: "Nogal — Ropa masculina que envejece bien",
  description:
    "Camisas, pantalones, chaquetas y calzado de hombre. Catálogo servido por una API en Laravel 12 con inventario por variante y almacén.",
  keywords: [
    "ropa masculina",
    "camisas hombre",
    "pantalones hombre",
    "Laravel 12",
    "API ecommerce",
  ],
  authors: [{ name: "Rolando Fonseca" }],
  openGraph: {
    type: "website",
    locale: "es_CO",
    title: "Nogal — Ropa masculina que envejece bien",
    description:
      "Escaparate del proyecto P7: un backend de ecommerce en Laravel 12 con catálogo, inventario, carrito y pedidos.",
    siteName: "Nogal",
  },
  twitter: {
    card: "summary_large_image",
    title: "Nogal — Ropa masculina que envejece bien",
    description:
      "Escaparate del proyecto P7: backend de ecommerce en Laravel 12.",
  },
  robots: { index: true, follow: true },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html
      lang="es"
      className={`${fraunces.variable} ${manrope.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}
