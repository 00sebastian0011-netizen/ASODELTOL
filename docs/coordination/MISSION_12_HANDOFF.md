# ASODELTOL — PROTOCOLO DE CONTINUIDAD (HANDOFF CODEX / GEMINI)
## Misión 12: Experiencia Visual Institucional (Institutional UX/UI)

- **Fecha de relevo:** 2026-10-22
- **Rama activa:** `feat/mission-12-institutional-ux`
- **Pull Request activa:** [PR #4](https://github.com/00sebastian0011-netizen/ASODELTOL/pull/4)
- **Commit base de main:** `fc3e0f957294c32223101f01200a00bc60940ec3`
- **Agente saliente:** Gemini
- **Agente entrante:** Codex (al restaurar créditos) o responsable humano
- **Estado de Misión 12:** IMPLEMENTADA, CORREGIDA SEGÚN REVISIÓN DE PR #4 Y VALIDADA (100% controles superados)

---

### 1. Resumen Ejecutivo y Aclaración de Identidad

Gemini asumió la continuidad de la Misión 12 respetando la línea base previa y sin realizar operaciones destructivas. En respuesta a la revisión técnica recibida en la PR #4, se corrigieron los cuatro puntos señalados, conservando todos los avances previos:

> **Aclaración sobre Identidad Visual:** La paleta cromática utilizada (Verde Tolima `#1B4D3E`, Oro Cálido `#C59B27`, grises neutros `#1E293B` y fondo `#F8FAFC`) constituye una **propuesta visual provisional** y elegante para el Aula Virtual ASODELTOL, sujeta a confirmación y aprobación formal de identidad gráfica por parte de la directiva de ASODELTOL. No se declara «oficial» hasta que exista dicha homologación.

---

### 2. Correcciones Técnicas Implementadas (Revisión PR #4)

1. **Responsive sin ocultar desbordamientos (`overflow-x`):**
   - Se eliminó del SCSS la regla `html, body { max-width: 100vw; overflow-x: hidden; }` que enmascaraba desbordamientos reales.
   - Se implementó contención semántica de elementos: medios adaptativos (`max-width: 100%`), tablas con scroll horizontal accesible (`.table-responsive`, `.no-overflow`) y paneles off-canvas ocultos (`.drawer:not(.show)`, `.message-drawer.hidden`) para que no proyecten espacio fuera de la ventana.
   - En `capture-moodle53-ux.mjs`, se implementó una inspección DOM profunda que evalúa `rect.right > window.innerWidth` en todos los nodos visuales activos. En las 12 capturas multi-dispositivo (1440px, 768px, 375px), el desbordamiento horizontal es de **0px (CORRECTO)**.

2. **Verificación rigurosa de Contraste y Privacidad:**
   - **`M12-ACCESSIBILITY-001`**: Se reemplazó la simple verificación del literal `#1B4D3E` por un cálculo matemático estricto de luminancia relativa y ratio de contraste bajo la fórmula oficial WCAG 2.1. Se verificaron las 5 combinaciones clave de la propuesta provisional, todas superando el umbral de 4.5:1 (WCAG AA):
     - Verde Tolima `#1B4D3E` sobre Fondo Blanco `#FFFFFF`: **9.64:1**
     - Texto Blanco `#FFFFFF` sobre Botón/Navbar `#1B4D3E`: **9.64:1**
     - Texto Principal `#1E293B` sobre Fondo `#F8FAFC`: **13.98:1**
     - Texto Validado `#14532D` sobre Fondo `#DCFCE7`: **8.30:1**
     - Texto Observado `#92400E` sobre Fondo `#FEF3C7`: **6.37:1**
     *Se declara explícitamente como control parcial de paleta, no como auditoría WCAG integral.*
   - **`M12-PRIVACY-001`**: Se corrigió el uso defectuoso de `-SimpleMatch`. Se implementó un escáner regex estático que busca claves privadas (`BEGIN ... PRIVATE KEY`), credenciales hardcodeadas y dominios no sintéticos en todo el código fuente de plugins y scripts. Cero hallazgos.

3. **Fallo real ante errores de verificación:**
   - `scripts/staging/run-institutional-ux.ps1` evalúa `$allPassed`. Si algún control no pasa, lanza una excepción (`throw`) y termina con código de salida no cero, garantizando que el orquestador y la CI fallen.
   - El bloque `finally` ineludible garantiza que el confinamiento (`lockdown` con `visible = 0` en curso y categoría) se ejecute SIEMPRE, incluso cuando ocurra una falla.

4. **Reversión atómica, no destructiva y validada (Revisión Final PR #4):**
   - **Almacenamiento en volumen escribible:** Se corrigió la ubicación del snapshot hacia `$CFG->dataroot` (`/var/www/moodledata/theme-snapshot-pre-m12.json`), evitando fallos de E/S en monturas de sólo lectura.
   - **Rechazo estricto sin alteración de estado:** Si el snapshot no existe, está corrupto o le faltan campos obligatorios (`captured_at`, `theme`, `brandcolor`, `scsspre`, `scss`, `site_fullname`, `site_shortname`), `m12_revert()` aborta inmediatamente con error (`m12_fail`), sin alterar la configuración de Moodle ni modificar la base de datos.
   - **Eliminación atómica post-restauración:** El archivo de snapshot se elimina mediante `unlink` **únicamente después** de que todas las operaciones de actualización en base de datos (`$DB->update_record`), escrituras de configuración (`set_config`) y purga de caché (`theme_reset_all_caches`) hayan concluido con éxito.
   - **Protección de línea base en Apply:** `m12_apply()` comprueba y valida la creación efectiva del snapshot (`filesize > 0`). Si la personalización M12 ya se encuentra aplicada en el sitio y no existe un snapshot previo, `Apply` aborta con error en lugar de capturar el estilo modificado como línea base o inventar valores.
   - **Siembra canónica controlada:** Se incorporó la fase `--phase=seed-snapshot` para inicializar deliberadamente el snapshot canónico original limpio (`site_fullname = 'ASODELTOL Moodle 5.3 Staging'`, `brandcolor = ''`) en entornos existentes que hayan aplicado M12 antes del sistema de snapshots, sin alterar el estilo visual activo.

---

### 3. Archivos Modificados en la Revisión

1. **`scripts/staging/moodle53-institutional-ux.php`**:
   - Eliminación de `overflow-x: hidden` en `html, body`.
   - Adición de contención off-canvas para drawers cerrados.
   - Snapshot ubicable en `$CFG->dataroot` con validación estricta de estructura y valores.
   - Revert atómico: rechaza con error si falta o está corrupto sin tocar Moodle; elimina snapshot solo tras restauración exitosa.
   - Apply estricto: valida persistencia de snapshot y rechaza sobreescribir línea base si M12 ya está activo sin snapshot.
   - Nueva fase CLI `--phase=seed-snapshot`.

2. **`scripts/staging/capture-moodle53-ux.mjs`**:
   - Escaneo DOM exhaustivo de elementos con desbordamiento (`rect.right > window.innerWidth`).
   - Filtrado inteligente de contenedores de scroll autorizados (`.table-responsive`) y paneles off-canvas cerrados.

3. **`scripts/staging/run-institutional-ux.ps1`**:
   - Funciones matemáticas `Get-RelativeLuminance` y `Get-ContrastRatio` (WCAG 2.1).
   - Escáner regex estático de seguridad y privacidad.
   - Manejador seguro de fases con captura de errores `Invoke-M12PhaseSafe`.
   - Incorporación de pruebas negativas automatizadas: `M12-REVERT-NEG-001`, `M12-REVERT-NEG-002`, `M12-APPLY-SAFE-001`.
   - Incorporación de prueba real de ciclo reversible: `M12-REVERT-POS-001`.
   - Lanzamiento de error (`throw`) si algún control falla.
   - Separación estricta de la fase `Verify` para que no ejecute `Apply` destructivo.
   - Garantía de confinamiento (`lockdown`) en bloque `finally`.

4. **`plugins/local/asodeltolstaff/styles.css`**:
   - Documentación explícita de propuesta cromática provisional en el encabezado.

5. **`docs/coordination/MISSION_12_HANDOFF.md`**:
   - Actualización del informe de continuidad con el desglose de las correcciones de la PR #4.

---

### 4. Resultados de Pruebas y Validación (10/10 Controles Superados)

1. **`scripts/staging/run-institutional-ux.ps1 -Phase Verify`**:
   - `[PASS] M12-THEME-001`: Boost configurado con paleta provisional Tolima (`#1B4D3E`) y SCSS activo sin alterar el núcleo.
   - `[PASS] M12-IDENTITY-001`: Nombre provisional del sitio configurado como Aula Virtual ASODELTOL.
   - `[PASS] M12-STAFF-001`: Módulo `local_asodeltolstaff` integrado con tabla responsive, insignias y tap targets $\ge 44\text{px}$.
   - `[PASS] M12-RESPONSIVE-001`: 12 capturas en 3 viewports sin desbordamiento horizontal ni recorte (0px).
   - `[PASS] M12-ACCESSIBILITY-001`: Verificación matemática de contraste WCAG AA ($\ge 4.5:1$) en 5 pares de color provisionales.
   - `[PASS] M12-PRIVACY-001`: Escaneo regex estático confirmando ausencia de claves privadas o credenciales fijas.
   - `[PASS] M12-REVERT-NEG-001`: Revert rechaza la operación con error y sin alterar la configuración cuando no existe snapshot.
   - `[PASS] M12-REVERT-NEG-002`: Revert rechaza la operación con error, sin alterar la configuración y conservando el snapshot cuando el archivo está corrupto.
   - `[PASS] M12-APPLY-SAFE-001`: Apply aborta la operación si M12 ya está aplicada y no existe snapshot previo para evitar falsear la línea base.
   - `[PASS] M12-REVERT-POS-001`: Ciclo real Apply -> Revert -> Apply restaura exactamente la configuración previa limpia, elimina el snapshot sólo tras el éxito y re-aplica la UX institucional verificando el nuevo respaldo.
   - **Estado final:** Curso sintético (id=2) y categoría en estado OCULTO (`visible = 0`).

2. **`npm run quality`**:
   - Catálogo AF8: VÁLIDO.
   - 32/32 pruebas unitarias de Node.js aprobadas (0 fallos).

3. **`scripts/quality/validate-repository.ps1`**:
   - Validación del repositorio completada sin errores.

4. **PHP Lint**:
   - Cero errores de sintaxis en `moodle53-institutional-ux.php`.

---

### 5. Comandos para Verificar Localmente

```powershell
# 1. Comprobar controles de Misión 12 y ciclo seguro de reversión (10 controles)
& .\scripts\staging\run-institutional-ux.ps1 -Phase Verify

# 2. Probar ciclo completo de Apply, Capture y Lockdown
& .\scripts\staging\run-institutional-ux.ps1 -Phase All

# 3. Probar reversión segura a snapshot previo
& .\scripts\staging\run-institutional-ux.ps1 -Phase Revert

# 4. Validar calidad e integridad del repositorio
npm run quality
& .\scripts\quality\validate-repository.ps1
```

---

### 6. Próximas Acciones Recomendadas

1. Revisar el nuevo commit publicado en la PR [#4](https://github.com/00sebastian0011-netizen/ASODELTOL/pull/4).
2. Confirmar que los checks de GitHub Actions pasaron exitosamente.
3. Proceder con la decisión de integración hacia `main` por parte del responsable técnico/humano.
