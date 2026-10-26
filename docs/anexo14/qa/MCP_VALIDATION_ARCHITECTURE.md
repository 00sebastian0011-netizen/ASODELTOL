# MCP privado de validación funcional ASODELTOL

## Diseño local

```text
Cliente MCP
    │ JSON-RPC autenticado existente
    ├── 4 herramientas VPS existentes (sin cambios de contrato)
    └── 5 herramientas de validación
          │ catálogo y cola JSON acotada
          ▼
   validation_service.py
          │ jobs/val-*.json, auditoría sanitizada
          ▼
   validation_executor.mjs (proceso separado en VPS)
          │ Playwright interno + runner registrado
          ▼
   Moodle producción
```

El MCP no abre shell, SQL, PHP, navegador ni sesión. Solo acepta `case_id` de
`production-test-catalog.json`, con un máximo de cinco casos por solicitud.
`run_validation` exige además una referencia de autorización específica que el
VPS valida contra un archivo privado con expiración y casos permitidos; el
Bearer ordinario de diagnóstico no concede por sí solo permiso de ejecución.
`run_validation` crea una solicitud local; el ejecutor reclama el trabajo y
actualiza el resultado. El ejecutor genera la sesión Playwright temporalmente
en el VPS mediante una bóveda de cuentas sintéticas protegida; no recibe ni
devuelve credenciales, cookies o tokens por MCP.

## Herramientas

| Herramienta | Salida | Límite |
| --- | --- | --- |
| `validation_catalog` | Casos compactos por caso o requisito | máximo 5 |
| `run_validation` | `validation_id` y estado | solo IDs registrados; deduplicación 5 min |
| `validation_status` | estado y contadores | un ID válido |
| `validation_results` | resultados sanitizados | máximo 5 |
| `validation_evidence` | metadatos de evidencia | sin bytes, rutas privadas ni estado de navegador |

Las cuatro herramientas existentes (`vps_system_health`, `moodle_versions`,
`upload_limits`, `deployment_status`) permanecen disponibles y sus rutas REST
no se modifican.

## Seguridad y límites

- Cuerpo MCP máximo: 16 KiB; respuesta máxima: 24 KiB; concurrencia HTTP: 8.
- Validación: cinco casos, cinco resultados/evidencias, URLs y configuración
  únicamente desde archivos privados del VPS.
- Ejecutor: una ejecución `--once`, Playwright headless, timeout configurable
  acotado a 30–120 s, sin `shell` y sin argumentos de código.
- Casos `upload` quedan `BLOCKED` hasta autorización posterior, aislamiento,
  respaldo y recuperación verificables.
- Auditoría JSONL solo con fecha, evento, ID, IDs de casos y estado.
- Los resultados distinguen método `playwright`, API/GET o `policy`; una
  respuesta de API no se presenta como prueba visual.

## Instalación futura en VPS

No se instala ni activa en esta entrega. En una ventana aprobada:

1. Copiar el catálogo, `validation_service.py`, `mcp_server.py`, el ejecutor y
   el runner desde un artefacto revisado.
2. Configurar `ASODELTOL_VALIDATION_CATALOG_FILE` y
   `ASODELTOL_VALIDATION_STATE_DIR` en un directorio privado del servicio.
3. Configurar `ASODELTOL_QA_ACCOUNT_FILE` y `ASODELTOL_QA_CONFIG_FILE` fuera del
   repositorio con permisos mínimos; nunca ponerlos en MCP o Git.
4. Verificar primero `python -m unittest discover -s tests/diagnostics`, ejecutar
   un caso sintético/blocked y comprobar que el estado no contiene secretos.
5. Activar el ejecutor por separado y verificar `tools/list`, `run_validation`,
   `validation_status` y `validation_results` con un lote de lectura.

El instalador prepara `/opt/asodeltol-diagnostics` para el MCP original y
`/opt/asodeltol-validation-worker` para el ejecutor Node/Playwright. La cola y
la evidencia sanitizada quedan en `/var/lib/asodeltol-validation`; las cuentas,
la configuración y la autorización con expiración quedan en
`/home/user/.config/asodeltol-validation` con permisos `user:user:600`.
El worker usa `asodeltol-validation-worker.service` más su timer independiente;
no se añade Playwright al servicio MCP de 128 MB/16 tareas.

El flujo local verificable es:

```bash
bash scripts/diagnostics/vps/install-diagnostics.sh validate
bash scripts/diagnostics/vps/install-diagnostics.sh prepare
```

`prepare` crea un manifiesto root-owned bajo `/var/lib/asodeltol-diagnostics`
antes de copiar archivos. La activación del MCP y del timer son pasos separados
y el ejecutor exige bóveda, configuración y autorización privada presentes.

## Reversión

Detener el ejecutor, revocar o retirar sus archivos de cuenta, conservar la
auditoría necesaria y restaurar la versión anterior del servidor MCP. No usar
`reset --hard`, borrar respaldos ni modificar Moodle. Las cuatro herramientas
VPS existentes deben seguir respondiendo; si el estado nuevo es inconsistente,
marcar la validación como `BLOCKED` y volver al artefacto anterior.
