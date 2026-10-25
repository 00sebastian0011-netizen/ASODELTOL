# Aula Virtual ASODELTOL

Repositorio de componentes, temas, catálogo AF8, pruebas y herramientas de despliegue para una **instalación existente** de Moodle. No incluye el núcleo de Moodle, `moodledata`, matrículas reales, respaldos ni secretos.

## Entrada recomendada para agentes y colaboradores

- **Instrucciones operativas vigentes:** [AGENTS.md](AGENTS.md) (corto, por riesgo).
- **Mapa de contexto por tarea:** [docs/agents/README.md](docs/agents/README.md) y [context-map.json](docs/agents/context-map.json).
- **Panorama generado, sin cifras duplicadas:** `npm run agent:brief`.

```bash
git status --short
npm run agent:brief
npm run quality
```

El resumen se construye a partir del repositorio actual. **No determina qué versión está desplegada en Hostinger.** Las pruebas PHPUnit de Moodle requieren staging aislado; `npm run quality` comprueba catálogo y pruebas Node, no certifica la aplicación completa.

## Estructura y fuentes

| Directorio | Contiene |
| --- | --- |
| `plugins/` | Componentes propios y copia versionada de tema, no core Moodle |
| `catalog/af8/` | Estructura académica fuente, sin rellenar insumos desconocidos |
| `docs/anexo14/MATRIZ_ASODELTOL_82.md` | 82 criterios ASODELTOL; **no** resultados de ASOCODEM-AF1 |
| `docs/anexo14/TRAZABILIDAD_82_2026-10-25.json` | Evidencias históricas/técnicas por ID y limitaciones |
| `docs/DECISIONES_PENDIENTES.md` | Políticas y decisiones que requieren aprobación |
| `scripts/deploy/` | Política y mecanismo de publicación por componentes; validar activación real del VPS |
| `tests/` | Pruebas transversales con datos sintéticos |
| `docs/adr/` | Decisiones arquitectónicas documentadas |

La formación AF8 tiene cinco unidades de 16 horas, siete grupos previstos de 50 y un catálogo preliminar con 50 ítems enumerados y cuatro recursos generales; documentos de origen mencionan también 80 ítems y nueve recursos. La discrepancia **continúa abierta** y no autoriza generar materiales ficticios.

## Cambio seguro

Se permite trabajar y publicar en GitHub cuando el usuario autorice la tarea. La autorización del repositorio **no** incluye automáticamente producción. Antes de desplegar un componente se confirma la versión activa, la política en `scripts/deploy/deploy-policy.json`, riesgos, respaldo y resultado en el servidor real.

La identidad visual cargada mediante Moodle, la configuración de la base de datos, `config.php`, `moodledata`, los cursos y los datos personales no se sincronizan desde este repositorio. No equiparar un workflow de clasificación con un despliegue exitoso.

## Documentación detallada

- [Requisitos](docs/REQUERIMIENTOS.md) y [decisiones pendientes](docs/DECISIONES_PENDIENTES.md).
- [Matriz ASODELTOL](docs/anexo14/MATRIZ_ASODELTOL_82.md) y [trazabilidad por requisito](docs/anexo14/TRAZABILIDAD_82_2026-10-25.json).
- [Operación y reversión](docs/OPERACION.md), [despliegue](scripts/deploy/README.md) y [seguridad](docs/SEGURIDAD.md).
- [Arquitectura](docs/ARQUITECTURA.md) y [coordinación entre agentes](docs/coordination/README.md).
- [Índice de contexto](docs/agents/README.md) para seleccionar solo los documentos necesarios.

**Regla de evidencia:** código publicado ≠ plugin instalado ≠ prueba local ≠ aceptación formal. Registrar versión, entorno y fuente antes de declarar avances.
