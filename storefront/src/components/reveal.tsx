"use client";

import { motion, useReducedMotion } from "motion/react";
import type { ReactNode } from "react";

/**
 * Revelado al entrar en pantalla.
 *
 * 600 ms es largo para un botón y correcto para una superficie de marketing:
 * el presupuesto de 300 ms aplica a la interfaz, no a un hero.
 *
 * Se anima `transform` como cadena completa y no el atajo `y`, porque el atajo
 * no se acelera por hardware y una lista de doce tarjetas lo nota.
 */
export function Reveal({
  children,
  delay = 0,
  className,
}: {
  children: ReactNode;
  delay?: number;
  className?: string;
}) {
  const reduce = useReducedMotion();

  return (
    <motion.div
      className={className}
      initial={{
        opacity: 0,
        // Nunca scale(0): nada aparece de la nada.
        transform: reduce ? "translateY(0px)" : "translateY(18px)",
      }}
      whileInView={{ opacity: 1, transform: "translateY(0px)" }}
      viewport={{ once: true, margin: "-80px" }}
      transition={{ duration: 0.6, delay, ease: [0.23, 1, 0.32, 1] }}
    >
      {children}
    </motion.div>
  );
}
