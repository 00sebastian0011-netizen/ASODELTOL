# Puente MCP local para Codex

Este paquete se instala en el computador que ejecuta Codex; no en el VPS.
Configure primero un alias SSH local llamado `asodeltol-vps-admin` que use una
clave privada dedicada y la huella conocida del VPS. La clave privada no se
incluye en este repositorio ni se copia al servidor.

El puente usa `ssh -T`, sin reenvío de agentes ni túneles. Las herramientas
`ssh_execute`, `ssh_read_file`, `ssh_write_file`, `ssh_upload_file`,
`ssh_service` y `ssh_session_status` se exponen por stdio a Codex. El VPS
registra cada orden no interactiva con `asodeltol-agent-command`.

Instale el directorio como complemento local de Codex sólo después de que el
administrador haya habilitado la clave con el instalador del VPS. Para revocar,
ejecute como root en el VPS `scripts/operations/vps/revoke-admin-ssh.sh`, retire
la clave privada del computador y desinstale el complemento local.
