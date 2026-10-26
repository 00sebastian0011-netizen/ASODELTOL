export const PRODUCTION_ORIGIN = 'https://aulaasodeltol.online';

export function safeProductionUrl(value, base = PRODUCTION_ORIGIN) {
  try {
    const url = new URL(value, base);
    if (url.protocol !== 'https:' || url.origin !== PRODUCTION_ORIGIN || url.username || url.password || url.hash) return null;
    for (const key of url.searchParams.keys()) if (/token|sesskey|password|secret|key|cookie/i.test(key)) return null;
    return url;
  } catch { return null; }
}

export function downloadIdentity({status, url, headers = {}, expectedSha256, expectedContentType, expectedFilename, bytes}) {
  const contentType = String(headers['content-type'] || '').split(';', 1)[0].trim().toLowerCase();
  const disposition = String(headers['content-disposition'] || '');
  const filenameToken = expectedFilename.toLowerCase();
  const filenameOk = Boolean(expectedFilename && (disposition.toLowerCase().includes(`filename="${filenameToken}"`) || disposition.toLowerCase().includes(`filename=${filenameToken}`)));
  const sha256 = typeof bytes === 'string' ? bytes : null;
  const ok = status === 200 && safeProductionUrl(url) !== null && /^[a-f0-9]{64}$/i.test(expectedSha256 || '')
    && expectedContentType && contentType === expectedContentType.toLowerCase()
    && /^[^\\/\r\n]{1,180}$/.test(expectedFilename || '') && filenameOk
    && /^[a-f0-9]{64}$/i.test(sha256 || '') && sha256.toLowerCase() === expectedSha256.toLowerCase();
  return {ok, reason: ok ? undefined : 'download_identity_mismatch', contentType, filenameOk, sha256};
}

export function activitiesComplete(checks, selectors) {
  return Array.isArray(checks) && checks.length > 0 && Array.isArray(selectors) && selectors.length > 0
    && checks.every(check => check.status === 200 && !check.loginRequired && !check.errorPage
      && selectors.every(selector => Number(check.matches?.[selector]) > 0));
}
