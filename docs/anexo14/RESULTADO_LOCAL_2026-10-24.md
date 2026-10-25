# Resultado local ASODELTOL — 24 de octubre de 2026

> Informe histórico de la primera fase. Sus conteos y pendientes fueron ampliados posteriormente. El estado actual está en [CIERRE_TECNICO_2026-10-24.md](CIERRE_TECNICO_2026-10-24.md) y [MATRIZ_ASODELTOL_82.md](MATRIZ_ASODELTOL_82.md). Se conserva este informe como evidencia de evolución.

Rama: `moodle-completo-2026-10-24`. Moodle 5.3+ (Build 20261021), PHP 8.4.27 y MySQL 8.4, en `http://127.0.0.1:8053`.

Se conservaron los cambios preparados y no preparados anteriores. No se ejecutó commit, push, despliegue, acceso al VPS ni modificación de aulaasodeltol.online. No se eliminaron bases, volúmenes, cursos, usuarios ni evidencias del staging.

## Entregables

- Matriz de 82 requisitos en `MATRIZ_ASODELTOL_82.md`, XLSX y JSON; fuentes originales localizadas en Descargas, con SHA256. ASOCODEM-AF1 permanece separado.
- Export CSV/XLSX ampliado con código y nombre de la acción y cuatro marcas UTC: inicio/fin programado y reportado; nulos conservados vacíos. No representan login ni tiempo trabajado.
- Cuatro pruebas unitarias adicionales: etiquetas de capacidades, denegación de exportación, aislamiento entre grupos y reporte vacío; prueba de consultas por lotes ampliada para validar marcas UTC y acción.
- Runner PHPUnit con copia privada, dataroot privado, nueva base y cuenta limitada; preflight habitual bloqueado por defecto.
- Pruebas reproducibles, capturas Edge y plantillas de insumos y aceptación institucional.

## Matriz

| Estado | Cantidad |
|---|---:|
| IMPLEMENTADO+VERIFICADO | 1 |
| IMPLEMENTADO SIN VERIFICAR | 11 |
| PENDIENTE | 26 |
| BLOQUEADO | 44 |

**Aceptación oficial: 0 acreditadas de 82.** El único criterio integral verificado en local es REQ-18-01 (beneficiario autenticado denegado por URL directa al curso oculto). La implementación parcial de otros criterios no se presenta como cumplimiento.

## Pruebas ejecutadas

| Lote | Resultado | Alcance |
|---|---|---|
| QA inicial | 11 PASS, 0 FAIL, 1 BLOCKED | Bloqueo de PHPUnit en la instalación habitual; evidencia conservada |
| QA HTTP posterior al cambio | 8 PASS, 0 FAIL, 0 BLOCKED | Login, invitado, CSV y XLSX reales |
| Servicios M11 | 18 PASS, 0 FAIL | Permisos, grupos, asistencia provisional, validación, historial, privacidad y reporte |
| Curso oculto | 1 PASS | Beneficiario sintético autenticado, URL directa denegada |
| Contenido de exports | 1 PASS | 2 filas y 18 columnas; igualdad CSV/XLSX, dos grupos, acción y marcas UTC |
| Edge real | 5 PASS, 0 FAIL | Login, staff a 1440/768/375 px, consola/red sin errores del recorrido |
| PHPUnit aislado | PASS | 5 pruebas, 27 aserciones; ejecución estricta con avisos y deprecaciones tratados como fallo |
| Comprobaciones estáticas | PASS | PHP lint de manager/export/test/contexto, sintaxis Python/Node y git diff --check |

Los lotes se solapan: no sumar sus contadores como requisitos cumplidos ni como pruebas únicas. Se conservan intentos preliminares bloqueados por rutas largas de Windows y preparación del navegador. El intento HTTP qa_20261024_172452 tuvo 5 PASS y 1 FAIL por timeout durante login; la verificación de 18 columnas intentada antes de terminar esa descarga también falló al leer la descarga anterior de 16 columnas. Los resultados de la tabla corresponden a las últimas ejecuciones completadas de cada lote.

## PHPUnit y seguridad

- Resultado final del runner: **PASS**; resumen: `build/staging/anexo14-isolated/20261024223508-summary.json`.
- `init.php` solo se ejecuta después de comprobar grants exclusivos, base vacía y rechazo del acceso a `moodle.mdl_course`. El config original y los volúmenes del staging no son el destino de las pruebas.
- Las tablas y entidades de PHPUnit pueden ser reiniciadas dentro de su base privada. No hay permiso de la cuenta de pruebas sobre `moodle.mdl_`.
- La primera ejecución dio 4/4 con un aviso y una deprecación. Se corrigieron las siete claves de idioma de capacidades en español/inglés y se migró la metadata a atributos PHPUnit; se añadió regresión para las etiquetas.
- Se conservaron las bases/cuentas de preparación y artefactos de intentos anteriores. No se ejecutaron borrados. Los secretos y perfiles privados están fuera del informe y excluidos de Git.

## Pendientes y límites

- Contenidos definitivos, licencias, originalidad, accesibilidad, presentación docente, reglas de aprobación/culminación y calendario requieren insumos institucionales.
- Confirmar modalidad e IA, proveedor de videoconferencia, grabaciones y fuente de asistencia efectiva. El registro provisional y los logs de acceso no la acreditan.
- Obtener diccionario autorizado de identidad/caracterización y Anexo 12; roles y usuarios definitivos, incluido SENA, quedan para la última fase.
- REQ-24-04 sigue sin reporte contractual completo: faltan accesos/cierres, inicio/fin de acción y disponibilidad para dudas. Las nuevas marcas son de sesiones formativas.
- Faltan carga concurrente autenticada, monitoreo 24/7 y recorridos completos por navegador/SO y rol. Edge de escritorio con emulación de tamaños no demuestra dispositivos o sistemas reales distintos.
- El DOCX contiene discrepancias de totales e instrucciones; no se resolvieron inventando contenido académico.

## Evidencia

Manifest con SHA256: `build/staging/anexo14-compliance/evidence-manifest.json`. Matriz editable: `build/staging/anexo14-compliance/ASODELTOL-matriz-82.xlsx`.

Navegador: `build/staging/anexo14-browser/1792880262699/summary.json`. HTTP: `build/staging/anexo14-qa/qa_20261024_173352.json`.

El contexto final del runner verifica curso y categoría ocultos y dos sesiones sintéticas. Verificación final independiente guardada en `build/staging/anexo14-compliance/final-context.txt`.
