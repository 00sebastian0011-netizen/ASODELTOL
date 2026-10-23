# ASODELTOL — continuidad de la Misión 12

## Estado

- Fecha de actualización: 2026-10-22.
- Rama: `feat/mission-12-institutional-ux`.
- Pull Request: [PR #4](https://github.com/00sebastian0011-netizen/ASODELTOL/pull/4).
- Base: `fc3e0f957294c32223101f01200a00bc60940ec3` (`main`, Misiones 01–11).
- Entorno: Moodle 5.3 local aislado en `http://127.0.0.1:8053`.
- Producción/VPS: no modificados.

La personalización visual de Boost permanece activa. La paleta verde/oro, tipografía y demás decisiones gráficas son una propuesta provisional hasta recibir aprobación institucional.

## Avances visuales preservados

- Login, navegación, panel del estudiante y tarjetas de cursos.
- Curso AF8 y estados visuales de sus cinco unidades oficiales.
- Adaptaciones para 1440, 768 y 375 px.
- Integración visual de `local_asodeltolstaff`, tablas responsive, insignias y objetivos táctiles.
- Capturador real mediante Edge headless y controles parciales de contraste.
- Doce capturas sanitizadas bajo `build/`, ignoradas por Git.

## Corrección final de seguridad

### Verify de solo lectura

`run-institutional-ux.ps1 -Phase Verify` ya no ejecuta `Apply`, `Revert`, `Lockdown`, eliminación de archivos ni creación de snapshots. Lee el estado y las evidencias existentes, calcula controles y compara antes/después:

- tema, color y nombre del sitio;
- hashes SHA-256 de SCSS y pre-SCSS;
- existencia, tamaño y SHA-256 del snapshot;
- visibilidad del curso y la categoría;
- comprobaciones de humo de las restricciones de Misión 10 y del plugin/datos sintéticos de Misión 11.

Si el curso o la categoría aparecen visibles, `Verify` falla y lo informa sin cambiar su estado. Dos ejecuciones consecutivas conservaron exactamente los hashes del staging y del snapshot.

### Pruebas destructivas aisladas

La nueva fase explícita:

```powershell
powershell -NoProfile -File scripts/staging/run-institutional-ux.ps1 -Phase TestReversibility
```

crea un directorio temporal único dentro de `moodledata/temp`, usa exclusivamente estado sintético respaldado por archivos y lo elimina al terminar. No escribe configuraciones Moodle. Comprueba:

1. rechazo de snapshot ausente sin cambios;
2. rechazo y conservación de snapshot corrupto;
3. `Apply` repetido sin sobrescribir el snapshot;
4. conservación de estado y snapshot ante un fallo previo al commit;
5. ciclo sintético `Apply → Revert → Apply`;
6. invariancia del tema, nombre, SCSS, snapshot y visibilidad del staging cotidiano.

### Protección del snapshot

- Se eliminó por completo `seed-snapshot`; el script no fabrica una supuesta configuración histórica.
- Los snapshots nuevos usan esquema versión 2, procedencia `pre_m12_live_capture` y checksum interno SHA-256.
- La creación usa modo exclusivo: nunca sobrescribe una ruta existente.
- Un snapshot ausente, corrupto, incompleto, heredado o sin integridad verificable se rechaza antes de cualquier escritura.
- `Apply` con M12 ya activa y sin snapshot verificable se rechaza; requiere recuperación manual desde una fuente fiable y autorización operativa.

El archivo actualmente presente en el staging conserva SHA-256 `c0d36448d4abfc8e398da44b900c995364ee4a7da2ab44e22ac72fe320c58b5e`. Se dejó intacto, pero se clasifica como heredado/no verificable porque el flujo anterior ejecutó `seed-snapshot`. No se presenta como respaldo histórico auténtico y no es elegible para reversión automática.

### Semántica de Revert

`m12_revert()` ya no se describe como una operación atómica de extremo a extremo:

- valida primero un snapshot versión 2;
- agrupa las escrituras de configuración y nombre del sitio en una transacción de base de datos;
- verifica los valores persistidos antes y después del commit;
- regenera la caché después del commit;
- conserva el snapshot ante cualquier error de escritura, verificación o caché;
- elimina el snapshot únicamente después de restaurar, verificar y regenerar la caché.

Esto constituye una restauración transaccional en base de datos y recuperable durante la finalización de caché/archivo. Una invocación explícita de `-Phase Revert` sigue siendo una operación mutable y no forma parte de `Verify`.

## Resultados focalizados

- `TestReversibility`: 6/6 controles aprobados en sandbox desechable.
- `Verify`: 10/10 controles aprobados.
- Dos ejecuciones consecutivas de `Verify`: estado y snapshot sin cambios.
- Hash del estado gobernado antes/después: `3d04450fbd40ec6982e0fc4085d4a5e5b59d3cee965bb79af31ffa0603e68ef4`.
- Fase retirada `seed-snapshot`: código de salida 1.
- Curso AF8 y categoría sintética: ocultos al finalizar.
- `npm run quality`: 32/32 pruebas automatizadas aprobadas.
- `scripts/quality/validate-repository.ps1`: validación completada sin errores.

## Operación segura

```powershell
# Inspección repetible de solo lectura sobre la configuración Moodle.
powershell -NoProfile -File scripts/staging/run-institutional-ux.ps1 -Phase Verify

# Pruebas negativas y ciclo en sandbox desechable, nunca en la configuración cotidiana.
powershell -NoProfile -File scripts/staging/run-institutional-ux.ps1 -Phase TestReversibility

# Capturas reales; abre temporalmente el curso y garantiza Lockdown al finalizar.
powershell -NoProfile -File scripts/staging/run-institutional-ux.ps1 -Phase Capture
```

No ejecutar `Apply` o `Revert` sobre un sitio activo sin revisar primero `status` y disponer de un snapshot versión 2 cuya procedencia sea verificable. El snapshot heredado actual requiere recuperación manual desde una copia fiable antes de autorizar una reversión.
