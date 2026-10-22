# Catálogo académico versionado

Este directorio contiene la fuente declarativa del diplomado AF8. No contiene contenido pedagógico, personas, archivos PDF, respaldos MBZ ni credenciales.

- `af8/catalog.json`: curso, recursos generales, cinco unidades, cincuenta componentes, siete grupos y reglas de progresión.
- `af8/access-control-matrix.json`: intenciones de acceso y pruebas negativas; no es un archivo importable de roles.
- `schema/af8-catalog.schema.json`: contrato estructural interoperable. Las reglas cruzadas se comprueban con el validador local.

Validación:

```powershell
npm run catalog:validate
npm test
```

Generación de un paquete local de aprovisionamiento, siempre oculto y dirigido a una categoría ya aprobada:

```powershell
node scripts/catalog/build-provisioning-bundle.mjs --category-idnumber=ASODELTOL-STAGING
```

El resultado se escribe en `build/provisioning/af8/`, ignorado por Git. El generador no se conecta a Moodle. Los CSV deben previsualizarse y validarse en **staging** por un operador autorizado; no deben usarse en producción mientras no exista una restauración de respaldo probada y aprobada.

Los campos nulos y estados `pendiente_*` son bloqueos deliberados: impiden convertir decisiones académicas faltantes en datos inventados.
