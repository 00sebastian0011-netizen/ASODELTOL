# MCP operativo de ASODELTOL

Servicio independiente de `asodeltol-diagnostics.service`, limitado a seis
operaciones: respaldo, despliegue de Staff 0.6.0, actualización Moodle,
publicación/verificación de política y rollback. No interpreta comandos ni
rutas recibidas por red.

## Instalación única en Hostinger

Copie esta carpeta desde un checkout confiable de `main`, sitúese en ella y
ejecute una sola vez como `root`:

```bash
./install-operations.sh
```

El instalador valida la instalación Moodle y el repositorio de despliegue,
instala archivos en `/opt/asodeltol-operations`, genera un token local de MCP,
activa `asodeltol-operations.service` y sólo informa éxito si `/healthz` y
systemd responden activos. No instala ni modifica `asodeltol-diagnostics`.

Antes de cada operación, un administrador del VPS debe generar una autorización
de 15 minutos, por ejemplo:

```bash
asodeltol-approve-operation backup pre-policy
asodeltol-approve-operation staff_deploy_0600 COMMIT_SHA
```

La salida es el `AUTHORIZATION_ID` de un único uso que debe acompañar a la
llamada MCP correspondiente. Las autorizaciones están vinculadas a operación y
parámetros; no existe una herramienta de shell, sudo ni rutas arbitrarias.

El respaldo guarda dump coherente de la base y archivo del plugin con hashes en
`/home/user/.local/state/asodeltol-operations/backups`. `rollback` sólo acepta
uno de esos identificadores verificados.
