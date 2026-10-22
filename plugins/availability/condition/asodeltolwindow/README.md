# `availability_asodeltolwindow` — contrato de diseño

Estado: propuesta no instalable.

## Responsabilidad

Condición de disponibilidad para actividades o secciones que restringe el acceso según una ventana configurable de días hábiles (máximo ocho días hábiles por módulo) y excepciones auditables. Se combina con las restricciones nativas de finalización y calificación; no las sustituye.

## Reglas de negocio obligatorias

### 1. Calendario de festivos de Colombia (Ley 51 de 1983 — Ley Emiliani)
El cálculo de días hábiles debe regirse estrictamente por la normativa colombiana:
- **Festivos fijos:** no se trasladan de fecha (1 de enero, 1 de mayo, 20 de julio, 7 de agosto, 8 de diciembre, 25 de diciembre).
- **Festivos trasladables (Ley Emiliani):** se trasladan al lunes siguiente a su acaecimiento (6 de enero - Reyes Magos, 19 de marzo - San José, 29 de junio - San Pedro y San Pablo, 15 de agosto - Asunción de la Virgen, 12 de octubre - Día de la Raza, 1 de noviembre - Todos los Santos, 11 de noviembre - Independencia de Cartagena).
- **Festivos religiosos móviles (vinculados al Domingo de Resurrección / Pascua):**
  - Jueves Santo y Viernes Santo (permanecen en su fecha en Semana Santa).
  - Ascensión del Señor, Corpus Christi y Sagrado Corazón de Jesús (se trasladan al lunes siguiente).
- El plugin debe incorporar una tabla o motor determinista de festivos colombianos versionado por año calendario, sin dependencias de servicios web externos en tiempo de ejecución.

### 2. Definición configurable de día hábil
- La jornada hábil se define por defecto de **lunes a viernes** (excluyendo festivos oficiales), con opción administrativa de habilitar sábados si el reglamento académico lo autoriza.
- La hora de corte para el vencimiento de la jornada es configurable (por defecto `23:59:59` en zona horaria oficial de Colombia, `America/Bogota`, equivalente a UTC-5 sin horario de verano).

### 3. Cálculo de plazos por grupo y por estudiante
- En una arquitectura de curso único con siete grupos (`AF8-G01` a `AF8-G07`), el plugin debe admitir **disparadores de inicio diferenciados por grupo** si las cohortes no comienzan de forma simultánea.
- Modos de inicio soportados:
  1. *Por fecha fija del grupo:* vinculada al inicio oficial de la cohorte.
  2. *Por finalización de la unidad anterior:* el cronómetro de 8 días hábiles arranca en el instante en que el estudiante aprueba y completa el módulo previo.

### 4. Excepciones auditables (Prórrogas)
- Toda excepción debe registrarse de manera inmutable:
  - Estudiante beneficiario y contexto (sección o módulo).
  - Número de días hábiles adicionales o nueva fecha límite UTC.
  - Justificación obligatoria y número de radicado/solicitud.
  - Actor que autoriza (usuario con capacidad específica).
  - Sello de tiempo UTC de creación.
- No se permite sobrescribir excepciones anteriores; una modificación o revocación crea un nuevo registro con referencia al anterior y emite un evento consumible por `local_asodeltolaudit`.

### 5. Estrategia de rendimiento y caché (MUC - Moodle Universal Cache)
- **Problema de degradación:** El método `is_available()` de la Availability API se invoca decenas de veces por página para cada usuario y módulo visible. Consultas a la base de datos en este método colapsarían el VPS de 2 vCPU ante 50 estudiantes concurrentes.
- **Solución oficial:**
  - El plugin implementará una definición de caché en `db/caches.php` utilizando MUC (`core_cache`).
  - Tipo de almacén: almacén de petición (*request cache*) o de sesión para las resoluciones de festivos y fechas de inicio del usuario.
  - Invalidación automática: ante la creación o revocación de una excepción, se purga la clave de caché del usuario afectado mediante eventos.

## Contrato técnico esperado

- Availability API (`\core_availability\condition`) y contextos de Moodle 5.3 LTS.
- Fechas persistidas en UTC y calendario colombiano versionado.
- Explicación accesible de permitido/bloqueado (*get_description*).
- Privacy API y Backup/Restore según el alcance aprobado.
- Pruebas PHPUnit exhaustivas de bordes temporales (cambios de mes, años bisiestos, Semana Santa, festivos trasladados) y Behat de interacción de usuario.

No iniciar la implementación de código hasta que la migración a Moodle 5.3 LTS esté completada en staging y se aprueben las DP-004 a DP-006.

