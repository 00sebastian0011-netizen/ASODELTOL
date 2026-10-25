#!/usr/bin/env node

import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import {fileURLToPath} from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const policy = JSON.parse(fs.readFileSync(path.join(scriptDirectory, 'deploy-policy.json'), 'utf8'));
const riskOrder = {none: 0, fast: 1, standard: 2, critical: 3};

function normalize(file) {
  return file.replaceAll('\\', '/').replace(/^\.\//, '');
}

function matches(relative, patterns) {
  return patterns.some((pattern) => relative === pattern || relative.startsWith(pattern));
}

export function classifyFiles(inputFiles, currentPolicy = policy) {
  const files = [...new Set(inputFiles.map(normalize).filter(Boolean))].sort();
  const result = {
    risk: 'none',
    deployable: false,
    manualRequired: false,
    components: {},
    ignored: [],
    infrastructure: [],
    reasons: [],
  };

  const raiseRisk = (risk) => {
    if (riskOrder[risk] > riskOrder[result.risk]) result.risk = risk;
  };

  for (const file of files) {
    if (matches(file, currentPolicy.criticalRepositoryPaths)) {
      result.infrastructure.push(file);
      result.manualRequired = true;
      raiseRisk('critical');
      continue;
    }

    const component = currentPolicy.components.find((item) =>
      file === item.source || file.startsWith(`${item.source}/`));
    if (!component) {
      result.ignored.push(file);
      continue;
    }

    const relative = file.slice(component.source.length).replace(/^\//, '');
    if (!relative || matches(relative, component.excluded)) {
      result.ignored.push(file);
      continue;
    }

    const componentResult = result.components[component.id] ??= {
      risk: 'none',
      files: [],
      reasons: [],
    };
    let fileRisk = 'standard';
    if (matches(relative, component.critical)) {
      fileRisk = 'critical';
      componentResult.reasons.push(`${relative}: cambio crítico`);
    } else if (matches(relative, component.fast)) {
      fileRisk = 'fast';
    }

    componentResult.files.push({path: file, relative, risk: fileRisk});
    if (riskOrder[fileRisk] > riskOrder[componentResult.risk]) componentResult.risk = fileRisk;
    raiseRisk(fileRisk);
    result.deployable = true;
  }

  for (const [componentId, componentResult] of Object.entries(result.components)) {
    const sourceChanges = componentResult.files
      .map((item) => item.relative)
      .filter((file) => file.startsWith('amd/src/') && file.endsWith('.js'));
    for (const source of sourceChanges) {
      const module = path.posix.basename(source, '.js');
      const expected = `amd/build/${module}.min.js`;
      if (!componentResult.files.some((item) => item.relative === expected)) {
        componentResult.risk = 'critical';
        componentResult.reasons.push(`${source}: falta ${expected} compilado en el mismo cambio`);
        result.reasons.push(`${componentId}: recurso AMD sin compilación versionada`);
        result.manualRequired = true;
        raiseRisk('critical');
      }
    }
    if (componentResult.risk === 'critical') result.manualRequired = true;
  }

  return result;
}

function changedFiles(base, head) {
  const output = execFileSync('git', ['diff', '--name-only', '--no-renames', `${base}..${head}`], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'inherit'],
  });
  return output.split(/\r?\n/).filter(Boolean);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  let files = [];
  const filesIndex = args.indexOf('--files');
  if (filesIndex !== -1) {
    files = args.slice(filesIndex + 1);
  } else {
    const baseIndex = args.indexOf('--base');
    const headIndex = args.indexOf('--head');
    if (baseIndex === -1 || headIndex === -1 || !args[baseIndex + 1] || !args[headIndex + 1]) {
      console.error('Uso: classify-changes.mjs --base <sha> --head <sha> | --files <rutas...>');
      process.exit(2);
    }
    files = changedFiles(args[baseIndex + 1], args[headIndex + 1]);
  }
  process.stdout.write(`${JSON.stringify(classifyFiles(files), null, 2)}\n`);
}
