# ASODELTOL — PROTOCOLO DE CONTINUIDAD (HANDOFF CODEX / GEMINI)
## Misión 12: Experiencia Visual Institucional (Institutional UX/UI)

- **Fecha de relevo:** 2026-10-22
- **Rama activa:** `feat/mission-12-institutional-ux`
- **Commit base de main:** `fc3e0f957294c32223101f01200a00bc60940ec3`
- **Agente saliente:** Gemini
- **Agente entrante:** Codex (al restaurar créditos)
- **Estado de Misión 12:** IMPLEMENTADA, VALIDADA Y DEMOSTRADA (100% de controles superados)

---

### 1. Resumen Ejecutivo del Relevo

Gemini asumió la continuidad de la Misión 12 respetando la línea base previa y sin realizar operaciones destructivas. Se completó el diseño e implementación de la identidad institucional para el Aula Virtual ASODELTOL en Moodle 5.3 LTS, integrando la paleta oficial (Verde Tolima `#1B4D3E`, Oro Cálido `#C59B27`, grises neutros de alto contraste WCAG AA), tipografía moderna (Inter / sistema optimizado), componentes de navegación, panel de estudiante (`/my/`), vista pedagógica del diplomado AF8 (`/course/view.php?id=2`), tarjetas de unidades (UT1 a UT5) con estados de bloqueo/desbloqueo, y el módulo de capacitadores `local_asodeltolstaff`.

Se resolvió la limitación de inspección visual en Windows mediante un arnés automatizado de Microsoft Edge headless con el protocolo Chrome DevTools (CDP) que capturó pares reales de "antes" y "después" en 3 resoluciones (1440px escritorio, 768px tableta, 375px móvil) sin desbordamiento horizontal (0px de overflow).

---

### 2. Archivos Creados y Modificados en el Repositorio

1. **`plugins/local/asodeltolstaff/styles.css`** (Nuevo):
   - Estilizado profesional del plugin propio `local_asodeltolstaff`.
   - Contenedores adaptativos `.table-responsive` y `.no-overflow` para evitar desbordamiento horizontal en tablas de asistencia.
   - Insignias semánticas (`.badge-draft`, `.badge-planned`, `.badge-observed`, `.badge-validated`).
   - Botones y controles de formulario con targets táctiles móviles $\ge 44\text{px}$.
   - Contraste visual accesible en estados hover y focus.

2. **`scripts/staging/moodle53-institutional-ux.php`** (Nuevo):
   - Fixture PHP CLI oficial para gestionar la experiencia visual en el contenedor `asodeltol-moodle53-webserver-1`.
   - Fases soportadas:
     - `--phase=status`: Consulta el tema activo (`boost`), color de marca, longitudes de SCSS y estado de visibilidad del curso.
     - `--phase=apply`: Inyecta la identidad visual en `theme_boost` mediante variables `$scsspre` y reglas compuestas `$scss`, actualiza el nombre del sitio a "Aula Virtual ASODELTOL" y purga cachés (`theme_reset_all_caches()`).
     - `--phase=revert`: Restaura la configuración estándar del tema Boost y limpia las personalizaciones.
     - `--phase=open`: Habilita temporalmente la visibilidad de curso y categoría para inspección de cuentas sintéticas.
     - `--phase=lockdown`: Garantiza que el curso sintético (id=2) y su categoría permanezcan en `visible = 0`.

3. **`scripts/staging/capture-moodle53-ux.mjs`** (Nuevo):
   - Herramienta Node.js pura (sin dependencias npm externas) para captura de evidencias vía Microsoft Edge headless y WebSocket CDP.
   - Autenticación automática de sesiones sintéticas (`af8.synthetic.g01` para estudiante, `asodeltol_admin` para administración/staff) gestionando cookies con prefijos dinámicos de Moodle (`MoodleSessionASODELTOLSTG53`).
   - Emulación de 3 viewports: Desktop (1440x900), Tablet (768x1024), Mobile (375x812).
   - Detección en tiempo de ejecución de desbordamiento horizontal (`scrollWidth > clientWidth`).

4. **`scripts/staging/run-institutional-ux.ps1`** (Nuevo):
   - Orquestador PowerShell estricto (`Set-StrictMode -Version Latest`) que implementa el ciclo de vida de la Misión 12: `Apply`, `Capture`, `Verify`, `Lockdown`, `Revert`.
   - Bloque `finally` de seguridad obligatorio que asegura que el curso y la categoría nunca queden expuestos.
   - Generación de los manifiestos de evidencia oficial: `build/staging/moodle53/mission-12-evidence.json` y `build/staging/moodle53/mission-12/mission-12-evidence.json`.

5. **`docs/coordination/MISSION_12_HANDOFF.md`** (Nuevo):
   - Este documento de continuidad para el retorno de Codex.

---

### 3. Funcionalidades Terminadas

- [x] **Identidad Institucional en Boost:** Paleta Verde Tolima (`#1B4D3E`), Oro Cálido (`#C59B27`), fondo institucional limpio `#F8FAFC`, texto de alto contraste `#1E293B`.
- [x] **Barra de Navegación (Navbar):** Marca destacada, contraste reforzado, enlaces accesibles con indicadores de foco.
- [x] **Página de Inicio de Sesión (`/login/index.php`):** Tarjeta centralizada con sombra suave, campos accesibles y presentación adaptativa en móvil.
- [x] **Panel del Estudiante (`/my/`):** Tarjetas de resumen del curso con bordes redondeados, indicadores de progreso visuales, elevación sutil en hover.
- [x] **Vista del Curso AF8 (`/course/view.php?id=2`):** Tarjetas para las cinco unidades temáticas (UT1 a UT5), badges de estado desbloqueado/bloqueado, alertas institucionales explicativas y casillas de finalización resaltadas.
- [x] **Módulo de Capacitadores (`local_asodeltolstaff`):** Tablas de asistencia y sesiones con scroll horizontal seguro en pantallas pequeñas, etiquetas de estado homologadas y botones táctiles.
- [x] **Inspección Visual Multi-dispositivo:** 24 capturas reales generadas (12 antes, 12 después) en `build/staging/moodle53/evidence/mission12/`.
- [x] **Cero desbordamiento horizontal:** 0px de overflow confirmado en los anchos de 1440px, 768px y 375px.
- [x] **Seguridad y Confinamiento:** El curso sintético (id=2) y su categoría terminaron en estado oculto (`visible = 0`).

---

### 4. Pruebas y Controles de Calidad Ejecutados

1. **`scripts/staging/run-institutional-ux.ps1 -Phase Verify`**:
   - `M12-THEME-001` [PASS]: Boost configurado con brandcolor `#1B4D3E` y SCSS activo sin tocar el núcleo.
   - `M12-IDENTITY-001` [PASS]: Nombre oficial "Aula Virtual ASODELTOL".
   - `M12-STAFF-001` [PASS]: Plugin con `.table-responsive`, `.badge-validated` y targets táctiles $\ge 44\text{px}$.
   - `M12-RESPONSIVE-001` [PASS]: 12 capturas completas en 3 viewports sin overflow horizontal.
   - `M12-ACCESSIBILITY-001` [PASS]: Contraste WCAG AA verificado.
   - `M12-PRIVACY-001` [PASS]: Cero contraseñas o tokens en el código.
   - Estado de curso y categoría: OCULTO (`visible=0`).
2. **`npm run quality`**:
   - Validación del catálogo AF8: VÁLIDO.
   - 32/32 pruebas unitarias de Node.js ejecutadas con éxito (0 fallos).
3. **`scripts/quality/validate-repository.ps1`**:
   - Validación integral del repositorio completada sin errores.
4. **PHP Lint**:
   - `docker exec asodeltol-moodle53-webserver-1 php -l /opt/asodeltol/scripts/moodle53-institutional-ux.php`: 0 errores de sintaxis.

---

### 5. Capturas de Evidencia Disponibles

Ubicación: `build/staging/moodle53/evidence/mission12/`
- **Antes (Línea base Moodle Boost sin estilizar):**
  - `before/login-desktop.png`, `login-tablet.png`, `login-mobile.png`
  - `before/dashboard-desktop.png`, `dashboard-tablet.png`, `dashboard-mobile.png`
  - `before/course-desktop.png`, `course-tablet.png`, `course-mobile.png`
  - `before/staff-desktop.png`, `staff-tablet.png`, `staff-mobile.png`
- **Después (Experiencia Visual Institucional ASODELTOL):**
  - `after/login-desktop.png`, `login-tablet.png`, `login-mobile.png`
  - `after/dashboard-desktop.png`, `dashboard-tablet.png`, `dashboard-mobile.png`
  - `after/course-desktop.png`, `course-tablet.png`, `course-mobile.png`
  - `after/staff-desktop.png`, `staff-tablet.png`, `staff-mobile.png`

---

### 6. Comandos para Verificar o Continuar

```powershell
# 1. Verificar estado actual del tema y curso
docker exec asodeltol-moodle53-webserver-1 php /opt/asodeltol/scripts/moodle53-institutional-ux.php --phase=status

# 2. Ejecutar suite de verificación de Misión 12
& .\scripts\staging\run-institutional-ux.ps1 -Phase Verify

# 3. Volver a generar capturas si se realizan cambios visuales
& .\scripts\staging\run-institutional-ux.ps1 -Phase Capture

# 4. Validar integridad de repositorio
npm run quality
& .\scripts\quality\validate-repository.ps1
```

---

### 7. Próximas Acciones Recomendadas para Codex

1. Revisar los commits en la rama `feat/mission-12-institutional-ux`.
2. Verificar la Pull Request hacia `main` (o crearla si no existe).
3. Confirmar que los artefactos visuales y los manifiestos de evidencia en `build/staging/moodle53/evidence/mission12/` satisfacen plenamente los requerimientos de la Misión 12.
4. Proceder con el cierre formal de la Misión 12 una vez validada por el responsable humano.
