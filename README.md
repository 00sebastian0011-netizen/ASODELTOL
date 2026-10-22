# Aula Virtual ASODELTOL

Repositorio de ingeniería para extender y operar de forma controlada la instalación **existente** de Moodle del Aula Virtual ASODELTOL. Este repositorio no contiene ni debe contener el núcleo de Moodle, `moodledata`, datos de estudiantes, respaldos o secretos.

## Estado

Las Misiones 01–09 dejaron un Moodle 5.3 local aislado con el curso AF8 piloto, siete grupos, datos exclusivamente sintéticos, recorrido funcional y una plantilla estructural reutilizable. La Misión 09 creó un segundo curso sintético oculto mediante backup/restore nativo, sin copiar estudiantes, entregas, calificaciones ni finalizaciones, y validó exportaciones nativas de participantes, progreso y calificaciones por grupo. No hay plugins instalables ni cambios aplicados a producción. Los directorios de `plugins/` son contratos de diseño para **Moodle 5.3 LTS**.

Objetivo formal de compatibilidad ([ADR-0004](docs/adr/0004-objetivo-moodle-53-lts-y-documentroot-public.md)): **Moodle 5.3 LTS y PHP 8.4**. La rama previa Moodle 5.0.10+ finalizó su soporte de seguridad el 5 de octubre de 2026. Moodle 5.3 LTS garantiza soporte extendido de parches hasta octubre de 2029. La actualización requiere la reconfiguración del servidor web (NGINX/CloudPanel) para apuntar el DocumentRoot exclusivamente a `/public` (cambio arquitectónico de Moodle 5.1+) y validar la base de datos (MariaDB $\ge$ 11.4 o MySQL $\ge$ 8.4) antes de desplegar código.


## Alcance funcional

- Diplomado AF8 de 80 horas, organizado en cinco unidades de 16 horas.
- Siete grupos de hasta 50 estudiantes, para 350 matrículas.
- Progresión secuencial por aprobación y completitud.
- Ventanas de máximo ocho días hábiles por módulo, con excepciones trazables.
- Roles operativos de administración, capacitación, estudiante e interventoría/auditoría.
- Seguimiento de matrícula, progreso, asistencia, calificaciones y actividad.
- Reportes descargables en Excel.
- Evidencia verificable de disponibilidad sincrónica de capacitadores.
- Integración con videoconferencia y grabaciones externas.
- Acceso mínimo necesario y protección de datos personales.

Las inconsistencias sobre el número de ítems y recursos se conservan abiertas; este repositorio no inventa contenidos académicos para resolverlas.

## Estructura

```text
catalog/       Fuente académica AF8, esquema y matriz de acceso
config/        Configuración versionada de herramientas, nunca secretos
docs/          Arquitectura, requisitos, seguridad, operación, ADR y coordinación
plugins/       Código fuente futuro de plugins propios, separado por tipo Moodle
scripts/       Validaciones y empaquetado local; no despliega a producción
tests/         Estrategia y activos transversales de prueba
```

Los plugins se desarrollan aquí y se prueban montándolos o copiándolos a una instalación **local o efímera** de Moodle 5.3. No se debe convertir este repositorio en una copia del árbol completo de Moodle.

## Inicio seguro

1. Leer [AGENTS.md](AGENTS.md), [requisitos](docs/REQUERIMIENTOS.md) y [decisiones pendientes](docs/DECISIONES_PENDIENTES.md).
2. Trabajar en una rama o `git worktree` exclusivo y reclamar los archivos según [coordinación](docs/coordination/README.md).
3. Usar únicamente datos sintéticos en desarrollo y pruebas.
4. Ejecutar la validación local:

   ```powershell
   npm run quality
   powershell -NoProfile -File scripts/quality/validate-repository.ps1
   ```

5. Para generar insumos locales de aprovisionamiento con una categoría de staging ya aprobada:

   ```powershell
   node scripts/catalog/build-provisioning-bundle.mjs --category-idnumber=ASODELTOL-STAGING
   ```

6. Para repetir en el Moodle local aislado la comprobación de plantilla y reportes:

   ```powershell
   powershell -NoProfile -File scripts/staging/run-template-reports.ps1 -Phase All
   ```

   El MBZ, los XLSX y la evidencia JSON quedan bajo `build/` y nunca se versionan.

7. Cuando existan plugins instalables, generar artefactos locales con:

   ```powershell
   powershell -NoProfile -File scripts/release/package-plugins.ps1
   ```

Los paquetes se escriben en `build/`, que está ignorado por Git. El empaquetado no instala ni publica nada.

## Principios de solución

1. **Core primero:** configurar Moodle antes de instalar o desarrollar.
2. **Plugin mantenido antes que plugin propio:** revisar compatibilidad, licencia, mantenimiento, privacidad y superficie de ataque.
3. **Sin cambios al núcleo:** toda extensión debe usar APIs y tipos de plugin soportados.
4. **Evidencia, no inferencia:** ni la asistencia ni el tiempo trabajado se calculan sólo con inicios y cierres de sesión.
5. **Privacidad desde el diseño:** minimización, capacidades por contexto, retención definida y trazabilidad.
6. **Despliegue reversible:** respaldo verificable, ensayo en staging, mantenimiento, validación y plan de reversión.

## Documentación principal

- [Arquitectura](docs/ARQUITECTURA.md)
- [Requisitos y trazabilidad](docs/REQUERIMIENTOS.md)
- [Roadmap](docs/ROADMAP.md)
- [Seguridad y privacidad](docs/SEGURIDAD.md)
- [Operación, pruebas y reversión](docs/OPERACION.md)
- [Aprovisionamiento reproducible de AF8](docs/APROVISIONAMIENTO_AF8.md)
- [Guía de auditoría independiente de la Misión 04](docs/AUDITORIA_MISION_04.md)
- [Decisiones pendientes](docs/DECISIONES_PENDIENTES.md)
- [Fuentes técnicas](docs/FUENTES.md)

## Límites operativos

No se autoriza desde este repositorio: acceso SSH, lectura de credenciales, cambios en el VPS, ejecución contra producción, `push`, despliegues, reinstalación de Moodle ni modificaciones del código fuente del núcleo.
