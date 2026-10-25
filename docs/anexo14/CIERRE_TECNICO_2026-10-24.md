# Cierre técnico local ASODELTOL — 24 de octubre de 2026

Esta entrega amplía el resultado histórico de la primera fase. Se trabajó únicamente en la rama `moodle-completo-2026-10-24`, Moodle 5.3+ / PHP 8.4.27 / MySQL 8.4, http://127.0.0.1:8053. No hubo commit, push, VPS ni cambios de producción. No se eliminaron bases, volúmenes, cursos, usuarios o evidencias.

## Resultado de los 82 criterios

| Estado | Cantidad |
|---|---:|
| IMPLEMENTADO+VERIFICADO | 20 |
| IMPLEMENTADO SIN VERIFICAR | 0 |
| PENDIENTE | 19 |
| BLOQUEADO | 43 |

**Aceptación oficial: 0/82 acreditadas.** Ver alcance, evidencia y responsable por fila en `MATRIZ_ASODELTOL_82.md` y `build/staging/anexo14-compliance/ASODELTOL-matriz-82.xlsx`. Una prueba de API aislada acredita esa función local; no acredita su interfaz ni producción. La matriz ASODELTOL se mantiene separada de la referencia ASOCODEM-AF1.

## Implementado

- Plugin 0.4.0 / 2026102402 preparado para el entorno local: reportes CSV/XLSX de sesiones, beneficiarios, consolidado y accesos; marcas UTC y fuentes nativas diferenciadas; citas de atención en calendario; guía de usuario visible; avance por curso/unidad y publicador limitado al grupo propio.
- Publicador con capacidad dedicada, matrícula y grupo, sesskey, contenido saneado, HTTPS y archivos del borrador propio; recursos creados con restricción nativa de grupo. La nueva capacidad está registrada con cero concesiones. No se modificaron roles institucionales definitivos.
- PHPUnit con cuenta/base/dataroot privados, grants exclusivos y rechazo comprobado a `moodle.mdl_course`. Se reutilizó el runtime seguro sin reinicializar la instalación operativa.
- Plantilla y validador de 54 componentes para el tercero; ZIP del plugin con manifest SHA256 y procedimiento de entrega. No se inventaron materiales, reglas académicas o aprobaciones.
- Zona horaria local corregida a America/Bogota; comprobación de fechas persistidas sin modificar sus instantes. La aparente duración cero inicial coincidía con la hora repetida del cambio estacional de Europe/London.

## Pruebas actuales

| Lote | Resultado | Evidencia |
|---|---|---|
| PHPUnit privado | 42 pruebas PASS, 150 aserciones, 0 fallos | `build/staging/anexo14-isolated/20261025002252-junit.xml` |
| Curso, archivos, foro, notas y grupos | 13 PASS, 0 FAIL, 0 BLOCKED; final oculto | `build/staging/anexo14-functional/20261025001844/summary.json` |
| Reportes CSV/XLSX | 6 PASS, 0 FAIL, 0 BLOCKED | `build/staging/anexo14-reports/20261025002307/summary.json` |
| Google Chrome, administración | 14 PASS, 0 FAIL, 0 BLOCKED; tamaños emulados | `build/staging/anexo14-browser/1792887804444/summary.json` |
| Google Chrome, ayuda | 5 PASS, 0 FAIL, 0 BLOCKED; tamaños emulados | `build/staging/anexo14-browser/1792883811343/summary.json` |
| Microsoft Edge Chromium, administración | 14 PASS, 0 FAIL, 0 BLOCKED; tamaños emulados | `build/staging/anexo14-browser/1792887704518/summary.json` |
| Microsoft Edge Chromium, archivos privados | 5 PASS, 0 FAIL, 0 BLOCKED; tamaños emulados | `build/staging/anexo14-browser/1792888200497/summary.json` |
| Microsoft Edge Chromium, ayuda | 5 PASS, 0 FAIL, 0 BLOCKED; tamaños emulados | `build/staging/anexo14-browser/1792883693245/summary.json` |

También se conservan pruebas M11, exportación de 18 columnas, curso oculto, cita con protección CSRF, mensajería bidireccional y seis controles del validador de insumos. Los lotes se solapan: no sumar como criterios únicos. El bloqueo de PHPUnit en la instalación habitual se mantiene intencionalmente; el runtime privado sí pasó.

## Integridad y fixtures conservados

- Estado final: curso y categoría ocultos; 13 usuarios, 7 grupos, 2 sesiones staff, 2 asistencias, 6 entradas de historial; 10 roles y 10 asignaciones. No hay plugins pendientes de actualización.
- Se conservaron una cita de calendario, cinco recursos sintéticos DOCX/XLSX/PPTX/PDF/PNG, una página, dos mensajes y las entregas/notas sintéticas 83/97 y foro G02 del piloto. También se conservaron los pequeños PNG de las pruebas de archivos privados del beneficiario G01 y sus borradores. Nunca copiarlos a producción.
- SHA256 coincidentes en repositorio, plugin instalado y copia probada para los 42 archivos: `build/staging/anexo14-compliance/code-parity.json`.

## Fallos, bloqueos y riesgos abiertos

- El primer ensayo concurrente de 50 sesiones independientes del mismo administrador produjo dos errores HTTP en la ruta curso. La repetición de esa ruta tuvo cero errores; no se demostró la causa inicial ni 50 beneficiarios distintos. Las mediciones están en `build/staging/anexo14-load/`; el criterio integral de rendimiento sigue abierto.
- Se conservan intentos preliminares fallidos/bloqueados de preparación, navegador y pruebas. Los resultados actuales de la tabla no borran ni convierten esos intentos en PASS.
- Falta contenido definitivo del tercero y revisión académica, licencias, accesibilidad y decisiones sobre modalidad/IA, evaluación, culminación y calendario. El validador registra 0 componentes READY y 54 BLOCKED por insumos ausentes.
- Anexo 12, datos y cuentas institucionales definitivas, proveedor/grabaciones, fuente de asistencia efectiva, CAU y disponibilidad 24/7 requieren responsables externos y validación del entorno final. Logs no equivalen a asistencia o trabajo.
- Faltan recorridos completos en otros sistemas, Firefox/Safari y hardware móvil. Emulación de tamaños no acredita esos equipos. Moodle y plugin conservan su estado de desarrollo; el paquete ALPHA no es certificación ni aceptación SENA.
- El DOCX contiene discrepancias 50/80, cuatro/nueve recursos y referencias de unidades copiadas; requieren aclaración institucional.

## Entrega

- Paquete: generar `build/staging/anexo14-release/local_asodeltolstaff-0.4.0.zip` con `python scripts/anexo14/package-plugin.py` después de clonar esta PR.
- Instrucciones: `ENTREGA_TERCERO_Y_VPS.md`. El usuario indicó que el tercero cargará los materiales; esta sesión no intervino en VPS.
- Evidencias verificables: `build/staging/anexo14-compliance/evidence-manifest.json`; contexto e integridad final en la misma carpeta.
