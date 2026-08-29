import { SiteNav } from "@/components/site-nav";
import { Hero } from "@/components/hero";
import { CategoryRail } from "@/components/category-rail";
import { ProductGrid } from "@/components/product-grid";
import { CollectionBanner } from "@/components/collection-banner";
import { UnderTheHood } from "@/components/under-the-hood";
import { SiteFooter } from "@/components/site-footer";

/**
 * Arquetipo: escaparate de producto. Hero, categorias, catalogo, colección
 * destacada, prueba tecnica y pie.
 *
 * "Bajo el capó" va antes del pie a proposito: quien baja hasta ahi ya vio la
 * tienda y esta preguntandose como esta hecha. Es el momento de responder.
 */
export default function Home() {
  return (
    <>
      <SiteNav />
      <main className="flex-1">
        <Hero />
        <CategoryRail />
        <ProductGrid />
        <CollectionBanner />
        <UnderTheHood />
      </main>
      <SiteFooter />
    </>
  );
}
