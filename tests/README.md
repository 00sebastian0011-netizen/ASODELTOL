# Pruebas

Las pruebas específicas se ubicarán en el directorio `tests/` de cada plugin, como exige Moodle. Este directorio raíz conserva activos transversales que no pertenecen a un solo componente.

```text
tests/
  catalog/     Validadores y generador AF8 con `node:test`
  behat/       Escenarios end-to-end entre varios plugins (futuros)
  fixtures/    Datos estrictamente sintéticos y pequeños
  performance/ Modelos y scripts de carga sin identidades reales (futuros)
```

Reglas:

- nunca apuntar PHPUnit, Behat o carga a producción;
- no copiar dumps ni exportaciones reales;
- nombres, documentos, correos y reuniones de fixtures deben ser inequívocamente ficticios;
- no versionar CSV/XLSX fuera de `tests/fixtures/`; allí sólo se permiten fixtures mínimos, explícitamente sintéticos y revisados;
- generar los CSV de aprovisionamiento en `build/`, que está ignorado, y nunca tratarlos como prueba de una importación realizada;
- pruebas deterministas de tiempo fijan reloj/zona/calendario;
- una prueba de logs nunca debe asumir que login/logout equivale a asistencia o trabajo.

La matriz completa está en `docs/OPERACION.md`.

Las pruebas transversales actuales se ejecutan sin dependencias externas:

```powershell
npm test
```
