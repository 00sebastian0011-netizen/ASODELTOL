# Puertas de despliegue

Esta carpeta no contiene una conexión ni una orden de despliegue. Sin inventario, rutas, estrategia de backups y aprobación de cambio, automatizar una copia remota sería inseguro.

El control preparado en esta etapa es `verify-release.ps1`, que opera únicamente sobre `build/` y valida:

- manifiesto generado por el empaquetador;
- presencia de cada archivo ZIP;
- checksum SHA-256;
- un único directorio raíz con el nombre correcto del plugin;
- ausencia de rutas absolutas o recorridos `..` dentro del ZIP.

Uso futuro:

```powershell
powershell -NoProfile -File scripts/deploy/verify-release.ps1 -ReleaseDirectory build/release-AAAAMMDD-HHMMSS
```

Después de esta puerta, un operador autorizado debe seguir `docs/OPERACION.md`. Verificar un paquete no autoriza instalarlo.
