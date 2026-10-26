# Conector VPS a ChatGPT — ASODELTOL (solo lectura)

**ESTADO: código preparado en GitHub; NO instalado, NO publicado ni conectado al VPS.**

El repositorio ASODELTOL es público. Nunca copiar a él claves SSH, credenciales, tokens, logs privados, datos de estudiantes ni inventarios sensibles.

## Qué proporciona

| Herramienta MCP / GPT Actions | Información sanitizada |
|---|---|
| vps_system_health / GET /v1/system | OS, uptime, RAM, disco, estado de NGINX/PHP y agentes |
| moodle_versions / GET /v1/versions | Versiones de archivos realmente instalados de Moodle, RSMax y Staff |
| upload_limits / GET /v1/uploads | Límites encontrados en configuración estática Vhost |
| deployment_status / GET /v1/deploy | SHA/estado de despliegue por componente; sin rutas o datos privados |

No ejecuta shell, SSH arbitrario, SQL ni comandos de despliegue. No lee config.php, base de datos, Moodledata, alumnos, contraseñas o contenido completo de registros. La configuración estática no prueba que una carga autenticada de video funcione.

## Paso 1 — Verificar la copia en GitHub

Desde la raíz del repositorio:

    python3 -m unittest discover -s tests/diagnostics -v
    python3 -m py_compile scripts/diagnostics/vps/probes.py scripts/diagnostics/vps/mcp_server.py
    bash -n scripts/diagnostics/vps/install-diagnostics.sh

GitHub Actions ejecuta estos ensayos, pero todavía debe revisarse el servicio en Hostinger real.

## Paso 2 — Instalar una vez con el agente autorizado de Hostinger

Revisar el usuario Linux y rutas vigentes antes de ejecutar. En una copia verificada de esta rama, dentro de scripts/diagnostics/vps:

    sudo bash ./install-diagnostics.sh prepare
    sudo bash ./install-diagnostics.sh activate
    sudo bash ./install-diagnostics.sh status

La primera orden solo prepara y genera una clave privada aleatoria en el VPS, sin imprimirla. La segunda activa un servicio systemd independiente, bajo usuario Linux no privilegiado, que escucha exclusivamente en 127.0.0.1:8766. No cambia el webhook de despliegue de 127.0.0.1:8765, NGINX ni Moodle.

Prueba local sin token:

    curl -fsS http://127.0.0.1:8766/healthz

Un resultado healthy acredita el servicio local, NO una conexión de ChatGPT.

## Paso 3 — Conectar desde ChatGPT con acceso autenticado

**MCP:** preferir Secure MCP Tunnel donde esté disponible. Como alternativa, el administrador puede incorporar ÚNICAMENTE al Vhost HTTPS el primer fragmento de nginx-location.conf, tras hacer respaldo, ejecutar nginx -t y recargar NGINX. Endpoint previsto, que NO está activo solo por estar documentado: https://aulaasodeltol.online/__asodeltol_diag/mcp

En ChatGPT web: Plugins → + → Add custom MCP server (solo cuando el plan y el espacio de trabajo lo permitan), autenticar y habilitar las cuatro herramientas. La compatibilidad con MCP personalizado depende del plan; no está garantizada para Plus. Documentación vigente: https://help.openai.com/en/articles/12584461-connecting-custom-mcp-servers-in-chatgpt

**Alternativa para Plus con GPT personalizado y Actions:** habilitar también el segundo fragmento HTTPS de nginx-location.conf, importar scripts/diagnostics/vps/openapi-actions.yaml en el editor de un GPT personalizado y configurar autenticación de API key tipo Bearer. El token va SOLO en el formulario privado de autenticación, nunca en este chat. Las acciones estarán disponibles en ese GPT, no automáticamente en esta conversación. Documentación: https://help.openai.com/en/articles/9442513-configuring-actions-in-gpts

Si el plan no permite MCP personalizado y tampoco quieres cambiar a un GPT con Actions, el agente de Hostinger puede generar una fotografía JSON sanitizada para compartirla aquí. Eso aporta contexto, pero no crea una conexión continua.

## Verificaciones obligatorias de seguridad

- Peticiones sin token o con token inválido → 401; origen ajeno → 403.
- Solo cuatro acciones. Herramientas tipo ejecutar comandos, leer archivos o consultar la base de datos deben ser inexistentes.
- Confirmar salida sin credenciales, rutas sensibles, información personal ni logs brutos.
- Validar que se distinguen archivos instalados, configuración estática y pruebas funcionales autenticadas.
- Descartar cualquier modificación de cursos, datos reales, plugins o proceso de despliegue.
- Solo activar acceso público por HTTPS con TLS válido y autenticación; nunca publicar el puerto 8766.
- No compartir ni subir el bearer token a GitHub o a un chat.

## Desactivar y revertir

    sudo bash ./install-diagnostics.sh disable

Si se activó NGINX o un túnel, quitar también la ruta externa y validar/reload NGINX. Rotar la clave cuando corresponda, desde el propio VPS.

## Alcance de los PR

Independiente de PR #18 y de las pruebas staging: únicamente scripts/diagnostics, tests/diagnostics, este documento y el workflow de QA. No altera Moodle core ni los archivos RSMax/ASODELTOL Staff. Fusionar no significa instalar o activar en Hostinger.

Este conector proporciona una visión técnica restringida y actualizable; no equivale a acceso total al VPS ni a aceptación institucional de los 82 requisitos.
