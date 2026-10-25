#!/usr/bin/env node
/**
 * ASODELTOL: resumen local, legible por agentes, sin red ni cambios de estado.
 * Los datos se derivan de fuentes versionadas; NO verifica producción.
 */
import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const ROOT = fileURLToPath(new URL('../../', import.meta.url));
const STATE_MAP = {
  'IMPLEMENTADO+VERIFICADO': 'VERIFICADO LOCAL',
  'IMPLEMENTADO SIN VERIFICAR': 'IMPLEMENTADO SIN VERIFICAR',
  PENDIENTE: 'PENDIENTE',
  BLOQUEADO: 'BLOQUEADO',
};

function from(root, relative) {
  return path.join(root, relative);
}

function load(root, relative) {
  return fs.readFileSync(from(root, relative), 'utf8');
}

export function readRequirementRows(markdown) {
  const rows = markdown.split(/\r?\n/)
    .filter((line) => /^\|\s*REQ-\d{2}-\d{2}\s*\|/.test(line))
    .map((line) => {
      const cells = line.split('|').map((value) => value.trim());
      return {id: cells[1], status: cells[4]};
    });
  if (rows.length !== 82 || new Set(rows.map((row) => row.id)).size !== 82) {
    throw new Error('Matriz: se esperan exactamente 82 IDs distintos');
  }
  for (const row of rows) {
    if (!(row.status in STATE_MAP)) {
      throw new Error('Estado no reconocido en matriz: ' + row.id + ' = ' + row.status);
    }
  }
  return rows;
}

export function assertTrackingMatches(rows, tracking) {
  if (tracking.total !== 82 || tracking.rows?.length !== 82 ||
      new Set(tracking.rows.map((row) => row.id)).size !== 82) {
    throw new Error('Trazabilidad: se esperan exactamente 82 IDs distintos');
  }
  const byId = new Map(tracking.rows.map((row) => [row.id, row]));
  const counts = {};
  for (const row of rows) {
    const observed = byId.get(row.id);
    if (!observed) throw new Error('ID ausente en trazabilidad: ' + row.id);
    const expected = STATE_MAP[row.status];
    if (observed.state !== expected) {
      throw new Error('Discrepancia ' + row.id + ': matriz=' + row.status +
        ', trazabilidad=' + observed.state);
    }
    counts[expected] = (counts[expected] || 0) + 1;
  }
  for (const [state, value] of Object.entries(tracking.counts || {})) {
    if ((counts[state] || 0) !== value) {
      throw new Error('Conteo distinto para ' + state + ': ' + value +
        ' vs ' + (counts[state] || 0));
    }
  }
  if (Object.values(tracking.counts || {}).reduce((sum, n) => sum + n, 0) !== 82) {
    throw new Error('Conteos de trazabilidad no suman 82');
  }
  return counts;
}

export function validateContextMap(root, map) {
  if (map.schema_version !== 1 || map.entrypoint !== 'AGENTS.md' ||
      !Array.isArray(map.domains) || !map.domains.length) {
    throw new Error('Mapa de contexto incompleto');
  }
  const ids = new Set();
  const sources = [map.entrypoint, ...map.historical_docs];
  for (const domain of map.domains) {
    if (!domain.id || ids.has(domain.id) || !domain.source ||
        !Array.isArray(domain.secondary)) {
      throw new Error('Dominio duplicado o sin fuente: ' + domain.id);
    }
    ids.add(domain.id);
    sources.push(domain.source, ...domain.secondary);
  }
  for (const relative of sources) {
    if (typeof relative !== 'string' || relative.startsWith('/') ||
        relative.split('/').includes('..') || relative.includes('\\') ||
        !fs.existsSync(from(root, relative))) {
      throw new Error('Ruta inexistente o insegura en mapa: ' + relative);
    }
  }
  return ids.size;
}

export function collectBrief(root = ROOT) {
  const map = JSON.parse(load(root, 'docs/agents/context-map.json'));
  const domains = validateContextMap(root, map);
  const rows = readRequirementRows(load(root, 'docs/anexo14/MATRIZ_ASODELTOL_82.md'));
  const tracking = JSON.parse(load(root, 'docs/anexo14/TRAZABILIDAD_82_2026-10-25.json'));
  const counts = assertTrackingMatches(rows, tracking);
  const catalog = JSON.parse(load(root, 'catalog/af8/catalog.json'));
  const plugin = load(root, 'plugins/local/asodeltolstaff/version.php');
  const version = plugin.match(/\$plugin->version\s*=\s*(\d+)\s*;/)?.[1];
  const release = plugin.match(/\$plugin->release\s*=\s*['"]([^'"]+)['"]/)?.[1];
  if (!version || !release) throw new Error('No se encontró la versión del plugin');
  if (catalog.units?.length !== catalog.course?.unit_count ||
      catalog.units.reduce((total, unit) => total + unit.components.length, 0) !==
        catalog.course.item_count ||
      catalog.general_resources?.length !== catalog.course.enumerated_general_resource_count) {
    throw new Error('Catálogo AF8: totales no coinciden con el inventario enumerado');
  }
  let head = 'sin repositorio Git disponible';
  try {
    head = execFileSync('git', ['rev-parse', '--short', 'HEAD'], {
      cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'],
    }).trim();
  } catch {
    // El resumen también sirve sobre archivos extraídos, sin .git.
  }
  return {
    head, version, release, domains, counts,
    total: rows.length,
    formal: tracking.formal_acceptances,
    units: catalog.course.unit_count,
    items: catalog.course.item_count,
    general: catalog.course.enumerated_general_resource_count,
    generalDeclared: catalog.course.declared_general_resource_count,
    groupCount: catalog.groups?.length ?? null,
  };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const b = collectBrief();
    process.stdout.write([
      'ASODELTOL | Git HEAD local: ' + b.head + ' | estado del VPS: NO VERIFICADO',
      'Plugin en Git: local_asodeltolstaff ' + b.release + ' (' + b.version + ')',
      'Anexo14: ' + b.total + ' IDs | verificados locales=' + (b.counts['VERIFICADO LOCAL'] || 0) +
        ' | pendientes=' + (b.counts.PENDIENTE || 0) +
        ' | bloqueados=' + (b.counts.BLOQUEADO || 0) +
        ' | aceptaciones formales=' + b.formal,
      'AF8: ' + b.units + ' unidades, ' + b.items + ' ítems enumerados, ' +
        b.general + ' recursos generales; documento también declara ' +
        b.generalDeclared + ' (discrepancia abierta)',
      'Contexto: ' + b.domains + ' dominios en docs/agents/context-map.json',
      'Antes de afirmar producción: verificar versión, tema, funciones y pruebas en el VPS autorizado.',
    ].join('\n') + '\n');
  } catch (error) {
    console.error('agent:brief FALLÓ (no ocultar discrepancia): ' + error.message);
    process.exitCode = 1;
  }
}
