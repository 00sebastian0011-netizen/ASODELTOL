# ADR-0001 — Repositorio de extensiones sin núcleo Moodle

- Estado: Aceptada
- Fecha: 2026-10-21
- Responsables: Equipo técnico ASODELTOL
- Requisitos/decisiones: RN-003, RN-004, RNF-002

## Contexto

Moodle 5.0.10+ ya está instalado en producción. Versionar otra copia del núcleo mezclaría responsabilidades, aumentaría conflictos de actualización y facilitaría parches no mantenibles.

## Opciones consideradas

1. Guardar un fork completo de Moodle.
2. Guardar sólo plugins, documentación, configuración de herramientas y scripts seguros.
3. Guardar parches sueltos contra el núcleo.

## Decisión

Adoptar la opción 2. El repositorio contiene código fuente de plugins propios bajo `plugins/`; cada artefacto se instala en la ruta estándar de una instancia separada de Moodle. No se aceptan cambios al núcleo.

## Consecuencias

- El entorno de pruebas debe aportar Moodle por otro mecanismo.
- La matriz de compatibilidad debe probarse contra versiones exactas.
- Los paquetes son pequeños, revisables y reversibles junto con el respaldo del sitio.
- Una necesidad que parezca exigir un core hack debe rediseñarse con APIs/plugins o elevarse como decisión.

## Verificación

La validación de repositorio detecta indicadores básicos de un núcleo copiado y la revisión confirma que cada cambio vive en un tipo de plugin soportado.

