# `report_asodeltol` — contrato de diseño

Estado: congelado provisionalmente — prioridad a Custom Reports de Moodle 5.3 LTS.

## Responsabilidad

Componente de reserva para entregar reportes consolidados contractuales especializados **únicamente si** las plantillas, filtros y reglas de acceso requeridas no pueden ser cubiertas mediante el subsistema nativo de Reportes Personalizados (*Custom Reports / Report Builder*) de Moodle 5.3 LTS.

## Estado de congelamiento y condiciones de reactivación

1. **Prioridad Core Primero:** Moodle 5.3 LTS incorpora mejoras en `core_reportbuilder` que permiten crear informes de cohortes, progreso, grupos y calificaciones con exportación programada o manual a XLSX/CSV y control de acceso granular por capacidades.
2. **Evaluación de brecha obligatoria:** No se escribirá código para este plugin hasta:
   - Recibir el catálogo contractual definitivo de reportes (columnas, fórmulas, filtros, audiencia y periodicidad según DP-012).
   - Prototipar los reportes solicitados en el Report Builder nativo de Moodle 5.3 en el entorno de staging.
3. **Alternativa arquitectónica prioritaria:** Si los reportes requieren fuentes de datos de plugins propios (por ejemplo, excepciones temporales o bitácora de capacitadores), la arquitectura preferida es exponer *Entities* y *DataSources* de Report Builder dentro de `local_asodeltolaudit` o `local_asodeltolstaff`, en lugar de un plugin `report` monolítico independiente.
4. **No descarte prematuro:** La estructura se conserva en el repositorio como salvaguarda contractual en caso de que la interventoría exija un formato con lógica de consolidación no soportable por el constructor nativo.

## Principios aplicables en caso de reactivación

- Consultas de sólo lectura, paginadas y filtradas por capacidad/contexto/grupo.
- Reutilizar `core_reportbuilder` y Data formats estándar para XLSX/CSV.
- No duplicar datos personales en tablas de reportería sin decisión explícita.
- Incluir fecha de corte, filtros y fuente de cada indicador.
- Registrar generación de exportaciones sensibles sin conservar el archivo temporal en disco más allá de la descarga.
- Neutralizar riesgo de fórmulas en celdas con texto controlado por usuarios (escapar `=`, `+`, `-`, `@`).


