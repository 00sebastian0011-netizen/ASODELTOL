# ADR-0005 — Modelo provisional de un curso con grupos separados y protocolo de piloto

- Estado: Aceptada
- Fecha: 2026-10-21
- Responsables: Equipo técnico ASODELTOL
- Requisitos/decisiones: RF-002, DP-007

## Contexto

Para capacitar a 350 estudiantes en siete grupos de 50 participantes en el Diplomado AF8 existen dos arquitecturas:

- **Opción A (Un curso con siete grupos separados):** Centraliza contenidos, actividades y libro de calificaciones en un único curso Moodle. El aislamiento entre grupos se logra mediante el modo de "Grupos separados" (`SEPARATEGROUPS`) forzado en el curso y la gestión de permisos por capacidades (`moodle/site:accessallgroups`).
- **Opción B (Siete cursos independientes replicados):** Cada grupo tiene su propia aula clonada. Ofrece aislamiento estricto por tabla (`courseid`), pero genera una sobrecarga de sincronización de contenidos: cualquier ajuste de fechas, errata o recurso debe replicarse siete veces de forma manual o mediante scripts. Además, fragmenta la reportería consolidada para la interventoría.

La auditoría técnica independiente advirtió con rigor que los "grupos separados" en Moodle constituyen un límite lógico de autorización por capacidades y roles, **no un aislamiento físico o de proceso en base de datos**. Si un rol o actividad se configura incorrectamente (por ejemplo, omitiendo forzar grupos en un foro), pueden producirse filtraciones de visibilidad entre cohortes.

## Opciones consideradas

1. Declarar definitiva la Opción A sin pruebas empíricas.
2. Descartar la Opción A y construir de inmediato la Opción B (siete cursos).
3. Adoptar provisionalmente la Opción A como diseño base, pero condicionar su aprobación definitiva a la superación de un piloto formal de segregación y privacidad en staging con datos sintéticos.

## Decisión

Adoptar la **Opción 3**:

1. Mantener provisionalmente la arquitectura de **un curso AF8 con siete grupos separados**, sincronizados desde cohortes del sistema (`AF8-G01` a `AF8-G07`) mediante `enrol_cohort`.
2. Configurar a nivel de curso:
   - `groupmode = 1` (Grupos separados).
   - `groupmodeforce = 1` (Forzar modo de grupo en todas las actividades).
3. Crear roles específicos:
   - **Capacitador:** basado en profesor sin permiso de edición, con `moodle/site:accessallgroups` prohibido (`Prohibit`).
   - **Interventoría:** rol de lectura transversal con `moodle/site:accessallgroups` permitido (`Allow`), sin permisos de edición ni calificación.
4. **Criterio de pase:** La arquitectura se mantendrá solo si el piloto en staging demuestra con datos sintéticos que ningún estudiante ni capacitador puede ver participantes, entregas, foros ni calificaciones de otros grupos. Si el piloto falla o la operación académica exige fechas dispares incompatibles con el libro de calificaciones unificado, se activará el plan de contingencia hacia la Opción B.

## Consecuencias

- Se preserva una única fuente de verdad para el contenido académico.
- La interventoría puede auditar el diplomado desde una vista unificada.
- Exige disciplina en la configuración del curso y pruebas automatizadas negativas (Behat) antes del lanzamiento.

## Verificación

Superación obligatoria de la matriz de pruebas de segregación (definida en `docs/ARQUITECTURA.md`) en el entorno de staging antes de la carga de contenidos reales.
