#!/usr/bin/env node

import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { readJsonFile, validateAccessMatrix, validateCatalog } from './lib/catalog-validator.mjs';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = resolve(scriptDirectory, '..', '..');

function parseArguments(argv) {
  const options = {
    catalog: resolve(repositoryRoot, 'catalog', 'af8', 'catalog.json'),
    accessMatrix: resolve(repositoryRoot, 'catalog', 'af8', 'access-control-matrix.json'),
    json: false,
    failOnWarnings: false
  };
  for (const argument of argv) {
    if (argument === '--json') {
      options.json = true;
    } else if (argument === '--fail-on-warnings') {
      options.failOnWarnings = true;
    } else if (argument.startsWith('--catalog=')) {
      options.catalog = resolve(argument.slice('--catalog='.length));
    } else if (argument.startsWith('--access-matrix=')) {
      options.accessMatrix = resolve(argument.slice('--access-matrix='.length));
    } else if (argument === '--help' || argument === '-h') {
      options.help = true;
    } else {
      throw new Error(`Argumento no reconocido: ${argument}`);
    }
  }
  return options;
}

function printHelp() {
  console.log(`Uso: node scripts/catalog/validate-catalog.mjs [opciones]

Opciones:
  --catalog=RUTA          Catálogo alternativo.
  --access-matrix=RUTA   Matriz de acceso alternativa.
  --json                  Salida JSON.
  --fail-on-warnings      Convierte advertencias en código de salida 1.
  -h, --help              Muestra esta ayuda.`);
}

function printHuman(results) {
  for (const [name, result] of Object.entries(results)) {
    console.log(`${name}: ${result.valid ? 'VÁLIDO' : 'INVÁLIDO'}`);
    for (const issue of result.issues) {
      const marker = issue.severity === 'error' ? 'ERROR' : 'AVISO';
      console.log(`  [${marker}] ${issue.code} ${issue.path}: ${issue.message}`);
    }
    console.log(`  Resumen: ${JSON.stringify(result.summary)}`);
  }
}

async function main() {
  const options = parseArguments(process.argv.slice(2));
  if (options.help) {
    printHelp();
    return;
  }

  const [catalog, accessMatrix] = await Promise.all([
    readJsonFile(options.catalog),
    readJsonFile(options.accessMatrix)
  ]);
  const results = {
    catalog: validateCatalog(catalog),
    access_matrix: validateAccessMatrix(accessMatrix)
  };
  if (options.json) {
    console.log(JSON.stringify(results, null, 2));
  } else {
    printHuman(results);
  }

  const hasErrors = Object.values(results).some((result) => !result.valid);
  const hasWarnings = Object.values(results).some((result) => result.warnings.length > 0);
  if (hasErrors || (options.failOnWarnings && hasWarnings)) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(`Validación no ejecutada: ${error.message}`);
  process.exitCode = 1;
});
