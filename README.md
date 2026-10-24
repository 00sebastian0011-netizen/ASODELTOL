# Aula Virtual ASODELTOL

Repositorio de ingeniería para extender y operar de forma controlada la instalación **existente** de Moodle del Aula Virtual ASODELTOL. Este repositorio no contiene ni debe contener el núcleo de Moodle, `moodledata`, datos de estudiantes, respaldos o secretos.

## Estado

El proyecto dispone de Moodle 5.3, el curso AF8, siete grupos, plantilla reutilizable, progresión secuencial y extensiones propias. El código versionado puede validarse en entornos aislados y publicarse o desplegarse mediante un flujo autorizado, trazable y reversible. La presencia de un componente en Git no demuestra por sí sola que esté instalado o activo en producción.

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
overlays/      Divergencias reproducibles y verificadas respecto de Moodle oficial
plugins/       Código fuente futuro de plugins propios, separado por tipo Moodle
scripts/       Validaciones, empaquetado y operaciones controladas
tests/         Estrategia y activos transversales de prueba
```

Los plugins se desarrollan aquí y se prueban primero en una instalación aislada de Moodle 5.3. Su despliegue a producción se realiza únicamente cuando la solicitud vigente lo autoriza, con respaldo, validación y reversión proporcionales al riesgo. No se debe convertir este repositorio en una copia del árbol completo de Moodle.

## Inicio seguro

1. Leer [AGENTS.md](AGENTS.md), [requisitos](docs/REQUERIMIENTOS.md) y [decisiones pendientes](docs/DECISIONES_PENDIENTES.md).
2. Revisar la rama y el estado de Git. Usar una rama o `worktree` separado cuando existan cambios concurrentes o riesgo de solapamiento.
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

7. Para generar artefactos locales de los plugins instalables:

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
- [Sincronización de producción con Git](docs/SINCRONIZACION_PRODUCCION_GIT.md)

## Autorizaciones y límites operativos

Git, GitHub, SSH, Hostinger, CloudPanel y los despliegues pueden utilizarse cuando la solicitud vigente los autoriza y existe acceso legítimo. La autorización para publicar o desplegar no permite exponer secretos o datos personales, alterar reglas académicas, ejecutar operaciones destructivas, cambiar servicios con costo ni modificar el núcleo de Moodle sin una autorización específica. Antes de intervenir producción se identifica la versión activa, se protege el punto de recuperación y se conserva una ruta de reversión.
