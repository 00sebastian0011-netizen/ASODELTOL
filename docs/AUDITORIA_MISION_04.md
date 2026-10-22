# Auditoría independiente de la Misión 04

## Cierre de observaciones de Gemini — 2026-10-22

- **Fidelidad académica verificada:** UT1–UT5 coinciden literalmente con las denominaciones aprobadas. Los recursos `0.01`–`0.04` también coinciden; `0.02` conserva la fuente original “Objetivo general y objetivos específicos”. No existía una diferencia real en `catalog.json`, por lo que no se modificó el catálogo. La discrepancia estaba únicamente en la redacción del informe/resumen de auditoría.
- **Inyección de fórmulas mitigada:** `csvCell()` rechaza texto cuyo primer carácter efectivo sea `=`, `+`, `-` o `@`, incluyendo espacios, controles y caracteres invisibles previos. No antepone apóstrofes ni altera identificadores/nombres válidos. Comas, comillas, saltos de línea y UTF-8 se conservan mediante entrecomillado CSV.
- **Importación alineada:** `--category-idnumber` es obligatorio y no tiene valor predeterminado `AF8`. La guía exige categoría preexistente, coincidencia exacta, UTF-8, separador coma, previsualización en staging, curso oculto y ausencia de estudiantes reales.
- **Regresión local:** 22 pruebas cubren fidelidad académica, duplicados, serialización CSV, inicios de fórmula, rechazo integral antes de escribir, estructura generada, directorio existente y determinismo.

## Mandato para Gemini

Realizar una revisión independiente y primero de sólo lectura. No editar archivos mientras exista un lock activo en `docs/coordination/locks/`; entregar hallazgos al autor para corrección. No acceder al VPS, respaldos, secretos ni datos reales; no ejecutar despliegues, `commit` o `push`.

## Comandos reproducibles

Desde la raíz del repositorio:

```powershell
npm run catalog:validate
npm test
powershell -NoProfile -File scripts/quality/validate-repository.ps1
node scripts/catalog/build-provisioning-bundle.mjs --category-idnumber=ASODELTOL-AUDIT --output=build/provisioning/af8-audit
```

El último comando sólo genera archivos locales ignorados por Git. Debe ejecutarse una vez en un directorio inexistente y una segunda vez para confirmar que rechaza la sobrescritura. El auditor elimina o conserva esos artefactos locales según su entorno; no los versiona ni los importa a Moodle.

## Lista de comprobación

1. Confirmar títulos exactos de UT1–UT5, 16 horas por unidad y total 80.
2. Confirmar diez códigos ordenados por unidad (`UTn.01`–`UTn.10`), 50 códigos y `stable_id` únicos.
3. Confirmar las asociaciones de tipos Página, Archivo, Carpeta/PDF, URL/H5P, Glosario y Cuestionario sin tipos ficticios.
4. Confirmar sólo cuatro recursos generales y la preservación explícita de DP-001/DP-002.
5. Confirmar siete grupos/cohortes AF8-G01–AF8-G07, capacidad declarada 50 y total 350.
6. Mutar copias temporales para verificar rechazo de duplicados, faltantes, orden, tipos, duración y referencias inexistentes.
7. Revisar que el CSV de curso cree un curso oculto, active completion, fuerce grupos separados y configure siete matriculaciones de cohorte con `addtogroup=1`.
8. Contrastar los encabezados CSV y el CLI documentado con Moodle 5.3 oficial; reportar cualquier campo no soportado.
9. Confirmar que `course-structure.json` se declara plano, no API/MBZ, y que no se afirma haber aprovisionado Moodle.
10. Confirmar que ninguna salida contiene nombres, correos, documentos, credenciales, hosts o rutas privadas.
11. Revisar la matriz de cuatro roles y las diez pruebas negativas: URL directa, participantes, notas, intentos/entregas, foros y mutaciones del auditor.
12. Evaluar la propuesta responsive/accesible sin exigir una aplicación separada de Moodle.

## Formato de hallazgos

Cada hallazgo debe incluir:

```text
Severidad: crítica | alta | media | baja
Archivo y línea:
Regla o requisito afectado:
Evidencia reproducible:
Impacto:
Corrección propuesta:
```

Separar errores funcionales de decisiones académicas pendientes. No recomendar inventar los 30 ítems, cinco recursos, notas, fechas, festivos, contenido o identidades que aún no tienen fuente autorizada.

## Criterio de aprobación

La misión puede aprobarse como preparación local cuando todas las pruebas pasan, los conteos son exactos, los artefactos son deterministas para las mismas entradas, no hay secretos/datos reales y el procedimiento sólo usa mecanismos oficiales verificables. Esto no aprueba producción ni demuestra el aislamiento real: ambos requieren restauración, Moodle 5.3 y piloto sintético en staging.
