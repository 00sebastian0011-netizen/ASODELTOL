# Simulación técnica AF8 — cinco unidades y contenido ficticio

**Corte de ejecución:** 29 de octubre de 2026, aprox. 15:43 COT (20:43 UTC).
**Ambiente:** GitHub Actions efímero, Moodle 5.3+, PHP 8.4, MySQL 8.4, curso sintético en `127.0.0.1:8053`.
**Origen:** [PR #64](https://github.com/00sebastian0011-netizen/ASODELTOL/pull/64), fusionado en `main` mediante `40bce9f9b5a610dc8c94a098edc15bee888c6c60`.
**Ejecución válida:** [GitHub Actions #40951051892](https://github.com/00sebastian0011-netizen/ASODELTOL/actions/runs/40951051892), conclusión **SUCCESS**.
**Estado:** **SIMULACIÓN TÉCNICA REPRODUCIBLE PASS**, **NO aceptación institucional**.

## Evidencias comprobadas

| Control | Observación | Resultado |
|---|---|---|
| Curso Moodle separado de AF8 real | Se creó sólo en la BD desechable `asodeltolqa`, usando `ASODELTOL_QA_ONLY=isolated-gh-runner` | PASS |
| Cinco unidades | UT1–UT5 con títulos canónicos de `catalog/af8/catalog.json` | PASS |
| Contenido real en módulo Page de Moodle | 50 páginas HTML ficticias; 50 rutas autenticadas renderizan el marcador persistido | 50/50 PASS |
| Grupos | G01–G07 de prueba; siete alumnos ficticios, uno por grupo | PASS |
| Roles de prueba | Beneficiario, Capacitador, Interventoría y SENA; diez usuarios sintéticos en total | PASS |
| Actividades evaluables | Cinco tareas `mod_assign`, una por UT, renderizadas en Moodle | 5/5 PASS |
| Comunicación | Foro nativo con grupos separados | PASS |
| Calificaciones | Calificación manual 87, con marcador ficticio, visible en informe de notas del beneficiario G01 | PASS |
| Restricción de acceso | G01 lee el recurso de su grupo y G02 no obtiene su contenido | 2/2 PASS |
| Navegación | Cinco primeras páginas de UT1–UT5 visibles para el beneficiario G01; portada contiene cinco títulos | 6/6 PASS |
| Suite autónoma del curso | `scripts/qa/check-af8-full-course-http.py` | **65/65 PASS, 0 FAIL** |
| QA de perfiles, notas, evidencias y prohibiciones | `scripts/qa/check-profile-http.py` en el mismo entorno efímero | **24/24 PASS, 0 FAIL** |
| Controles CI de repositorio | `Catalogo y seguridad` + `Permisos de perfiles` | **2/2 PASS** |

**Total en la ejecución aislada:** 89 verificaciones HTTP auténticas superadas (65 del curso + 24 de perfiles). No sumar estos casos como 89 requisitos contractuales distintos.

### Archivos de prueba

- [`scripts/qa/af8-full-course-fixture.php`](../../scripts/qa/af8-full-course-fixture.php): crea exclusivamente curso, grupos, actividades y datos sintéticos; rechaza entorno no aislado.
- [`scripts/qa/check-af8-full-course-http.py`](../../scripts/qa/check-af8-full-course-http.py): verifica por HTTP autenticado contenido persistido, navegación, permisos, calificación, foro y tareas.
- [`.github/workflows/qa-moodle53-http.yml`](../../.github/workflows/qa-moodle53-http.yml): entorno efímero y suite anterior de privacidad.
- [Resultados y logs de GitHub Actions](https://github.com/00sebastian0011-netizen/ASODELTOL/actions/runs/40951051892): incluye resumen JSON saneado `qa-af8-full-summary.json` como artefacto temporal de siete días.

### Historia de corrección

Las primeras ejecuciones fallaron por errores auténticos del fixture: tipo de dato del ID de caché, conteo del foro de anuncios nativo y contenido `mod_page` no persistido por CLI. Se corrigieron sin relajar ninguna aserción funcional: el último intento confirmó las 50 páginas efectivamente renderizadas. Los intentos fallidos se mantienen visibles en GitHub Actions; sólo el último es evidencia PASS.

## Límites contractuales: NO dar por aprobados

Esta simulación **no crea ni acredita contenido académico institucional**, ni entrega licencias, originalidad mínima, videos/subtítulos/AAC aprobados, presentaciones/hojas de vida docentes, ni referencias académicas definitivas. El catálogo todavía conserva avisos sobre 54 recursos/componentes sin contenido aprobado, cinco notas aprobatorias indefinidas y discrepancias de 50/80 ítems y cuatro/nueve recursos.

Tampoco demuestra ni sustituye:

- `REQ-08-05`: identificar y aprobar los cinco componentes contractuales **por módulo** y publicar los materiales definitivos; los cinco títulos de UT no bastan.
- Calificaciones mínimas, finalización y cálculo institucional de ocho días hábiles, calendario de festivos y excepciones autorizadas.
- CAU real, notificaciones externas y SLA de soporte durante la ventana comprometida.
- Grabaciones, videoconferencia real, actividad sincrónica y asistencia válida; los logs de acceso no acreditan presencia.
- Las cuatro plantillas oficiales de reportes del Anexo 12, sus columnas y fuentes.
- Rendimiento simultáneo en VPS, disponibilidad en toda la ventana y restauración integrada probada.
- Los 14 criterios condicionales sin aprobación institucional explícita de aplicabilidad.
- Aceptación oficial de los 68/82 criterios por interventoría.

**Cierre correcto:** esta simulación de un curso sintético completo en estructura y navegación **está terminada y PASS**. La acreditación integral del Anexo 14 **sigue pendiente**, sin falsificar resultados `PASS`, `N/A` o firmas.

## Estado del despliegue y continuidad

La intervención del [PR #62](https://github.com/00sebastian0011-netizen/ASODELTOL/pull/62) documentó la creación en **producción** de UT2–UT5 vacías/ocultas y la preservación de matrículas, grupos y notas reales. Su estado no se infiere de este nuevo CI; el ensayo aquí descrito existe **solamente en el runner temporal**.

En GitHub, el commit de cambios del formulario de satisfacción `fe6a9114e5bb605a51c2e173c726c448057f4a80` fue clasificado como **deployable=true, manualRequired=false**, con [evidencia del flujo #40947039767](https://github.com/00sebastian0011-netizen/ASODELTOL/actions/runs/40947039767). Esta clasificación **no demuestra por sí misma despliegue efectivo ni paridad SHA en el VPS**. Para certificarlo hace falta una lectura del agente/MCP SSH de producción, comparando hashes de archivos plugin/theme, SHA registrado y comprobación HTTP autenticada sin PII.

El PR #64 sólo añade scripts/workflow de QA y no modifica código operativo Moodle, tema o plugin. No debe dispararse una sincronización de infraestructura ni repoblar AF8 real para dar por completado el cierre.

**Fuentes de seguimiento:** [68 requisitos + 14 condicionales](MATRIZ_PENDIENTES_68_2026-10-29.md), [plan T01–T16](PLAN_PRUEBAS_PENDIENTES_2026-10-29.md) y [seguimiento postauditoría](SEGUIMIENTO_POSTAUDITORIA_2026-10-29.md).
