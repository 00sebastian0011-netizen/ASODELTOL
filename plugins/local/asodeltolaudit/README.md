# `local_asodeltolaudit` — contrato de diseño

Estado: propuesta no instalable.

## Responsabilidad

Registrar hechos de negocio ASODELTOL que requieren trazabilidad adicional: aprobación/revocación de excepciones, validación de disponibilidad y correcciones justificadas. Los logs estándar de Moodle continúan siendo la fuente de eventos técnicos.

## Principios técnicos

- **Alineación con la API de Eventos de Moodle:** Cada hecho auditable debe definirse como una subclase Frankenstyle de `\core\event\base` (por ejemplo, `\local_asodeltolaudit\event\exception_granted`, `\local_asodeltolaudit\event\staff_availability_validated`). Los suscriptores u observers se declaran en `db/events.php`.
- **Historia anexable (*Append-only*):** Las tablas de auditoría son de solo inserción. Una corrección o revocación crea un nuevo registro con referencia al identificador anterior, actor, motivo y sello de tiempo UTC.
- **Entidades para Report Builder:** Para evitar plugins de reportería innecesarios, este plugin expondrá fuentes de datos (*Entities / DataSources*) mediante `core_reportbuilder`, permitiendo que el reporte nativo de Moodle 5.3 consulte las excepciones de negocio.
- **Privacy API y Retención Legal Obligatoria (Ley 1581 de 2012):** La implementación de `\core_privacy\local\metadata\provider` declarará la base jurídica de conservación legal. Las solicitudes de eliminación de datos de estudiantes no borrarán registros de auditoría de excepciones o notas requeridos contractualmente para certificación; se aplicará anonimización de identificadores o bloqueo de supresión con justificación de custodia legal.
- **Acceso por capacidades:** Consultas protegidas por capacidades de contexto (`local/asodeltolaudit:viewaudit`, `local/asodeltolaudit:manageexceptions`).

## Fuera de alcance

- Replicar indiscriminadamente los logs técnicos estándar de Moodle (`logstore_standard`).
- Prometer inmutabilidad criptográfica absoluta frente a superusuarios con acceso directo a la consola de base de datos.
- Sustituir un SIEM corporativo o firma digital cualificada si el contrato no lo exige.

No implementar hasta migrar a Moodle 5.3 LTS y cerrar DP-013 y DP-018.

