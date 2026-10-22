# Plugins propios

Árbol fuente de componentes ASODELTOL. La estructura bajo `plugins/` replica la ruta de instalación relativa al directorio raíz de Moodle:

| Fuente en este repositorio | Destino en Moodle | Componente previsto | Estado |
|---|---|---|---|
| `plugins/availability/condition/asodeltolwindow` | `availability/condition/asodeltolwindow` | `availability_asodeltolwindow` | Prioridad técnica (Días hábiles / Festivos Colombia) |
| `plugins/local/asodeltolaudit` | `local/asodeltolaudit` | `local_asodeltolaudit` | Prioridad técnica (Eventos core y trazabilidad) |
| `plugins/local/asodeltolstaff` | `local/asodeltolstaff` | `local_asodeltolstaff` | Condicionado a brecha en piloto (Presencia docente) |
| `plugins/report/asodeltol` | `report/asodeltol` | `report_asodeltol` | **Congelado provisionalmente** (Prioridad a Report Builder de Moodle 5.3) |

Estos directorios son contratos de diseño para **Moodle 5.3 LTS**. No contienen `version.php` deliberadamente y no son instalables todavía. No deben empaquetarse ni copiarse a una instalación hasta completar la migración de la plataforma a 5.3 LTS y cumplir la definición de terminado de `AGENTS.md`.

No se incorporan aquí plugins terceros. Sus paquetes se obtienen de fuentes oficiales, se verifican y registran en el manifiesto de una release sin vendorizarlos automáticamente en Git.

