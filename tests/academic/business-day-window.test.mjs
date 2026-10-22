import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import test from 'node:test';

import {
  ASODELTOL_TIME_ZONE,
  calculateBusinessDayWindow,
  isBusinessDate,
  localDateFromInstant,
} from '../../scripts/academic/business-day-window.mjs';

const SYNTHETIC_HOLIDAYS = ['2026-07-20', '2026-07-27'];

test('interpreta el instante sintético en America/Bogota antes de contar', () => {
  assert.equal(localDateFromInstant('2026-07-18T02:30:00Z'), '2026-07-17');
});

test('calcula ocho días hábiles sintéticos excluyendo el día disparador', () => {
  const window = calculateBusinessDayWindow({
    startInstant: '2026-07-18T02:30:00Z',
    businessDays: 8,
    includeStart: false,
    holidays: SYNTHETIC_HOLIDAYS,
  });

  assert.deepEqual(window, {
    timeZone: ASODELTOL_TIME_ZONE,
    startLocalDate: '2026-07-17',
    endLocalDate: '2026-07-31',
    businessDays: 8,
    includeStart: false,
    holidays: SYNTHETIC_HOLIDAYS,
    calendarDaysElapsed: 14,
  });
});

test('mantiene abierta la alternativa sintética que incluye el día disparador', () => {
  const window = calculateBusinessDayWindow({
    startLocalDate: '2026-07-17',
    businessDays: 8,
    includeStart: true,
    holidays: SYNTHETIC_HOLIDAYS,
  });

  assert.equal(window.endLocalDate, '2026-07-30');
  assert.equal(window.calendarDaysElapsed, 13);
});

test('no cuenta sábados, domingos ni festivos declarados por el escenario', () => {
  assert.equal(isBusinessDate('2026-07-18', SYNTHETIC_HOLIDAYS), false);
  assert.equal(isBusinessDate('2026-07-19', SYNTHETIC_HOLIDAYS), false);
  assert.equal(isBusinessDate('2026-07-20', SYNTHETIC_HOLIDAYS), false);
  assert.equal(isBusinessDate('2026-07-21', SYNTHETIC_HOLIDAYS), true);
});

test('un inicio sintético en fin de semana comienza a contar el lunes hábil', () => {
  const window = calculateBusinessDayWindow({
    startLocalDate: '2026-07-18',
    businessDays: 8,
    includeStart: true,
    holidays: [],
  });

  assert.equal(window.endLocalDate, '2026-07-29');
});

test('no infiere festivos que el escenario no haya declarado', () => {
  const withHolidays = calculateBusinessDayWindow({
    startLocalDate: '2026-07-17',
    businessDays: 8,
    includeStart: false,
    holidays: SYNTHETIC_HOLIDAYS,
  });
  const withoutHolidays = calculateBusinessDayWindow({
    startLocalDate: '2026-07-17',
    businessDays: 8,
    includeStart: false,
    holidays: [],
  });

  assert.equal(withHolidays.endLocalDate, '2026-07-31');
  assert.equal(withoutHolidays.endLocalDate, '2026-07-29');
});

test('rechaza zona horaria, política inclusiva y fechas ambiguas', () => {
  assert.throws(() => calculateBusinessDayWindow({
    startLocalDate: '2026-07-17',
    includeStart: false,
    timeZone: 'UTC',
  }), /America\/Bogota/);
  assert.throws(() => calculateBusinessDayWindow({
    startLocalDate: '2026-07-17',
  }), /includeStart/);
  assert.throws(() => calculateBusinessDayWindow({
    startLocalDate: '17-07-2026',
    includeStart: false,
  }), /YYYY-MM-DD/);
});

test('rechaza instantes ambiguos sin Z ni offset explícito', () => {
  assert.throws(
    () => localDateFromInstant('2026-07-17T02:30:00'),
    /explicit offset or Z/i,
  );
  assert.throws(
    () => localDateFromInstant('2026-07-17 02:30:00'),
    /explicit offset or Z/i,
  );
  assert.throws(
    () => localDateFromInstant('2026-07-17'),
    /explicit offset or Z/i,
  );
  assert.throws(
    () => localDateFromInstant(1721269800000),
    /explicit offset or Z/i,
  );
  assert.throws(
    () => calculateBusinessDayWindow({
      startInstant: '2026-07-17T02:30:00',
      businessDays: 8,
      includeStart: false,
    }),
    /explicit offset or Z/i,
  );
  assert.throws(
    () => localDateFromInstant('2026-02-31T02:30:00Z'),
    /not a valid calendar date/i,
  );
  assert.throws(
    () => localDateFromInstant('2026-07-18T25:00:00Z'),
    /not a valid ISO-8601 instant/i,
  );
});

test('interpreta instantes equivalentes con diferentes offsets de forma determinista', () => {
  const utcInstant = '2026-07-18T02:30:00Z';
  const bogotaInstant = '2026-07-17T21:30:00-05:00';
  const cairoInstant = '2026-07-18T04:30:00+02:00';
  const tokyoInstant = '2026-07-18T11:30:00+09:00';

  assert.equal(localDateFromInstant(utcInstant), '2026-07-17');
  assert.equal(localDateFromInstant(bogotaInstant), '2026-07-17');
  assert.equal(localDateFromInstant(cairoInstant), '2026-07-17');
  assert.equal(localDateFromInstant(tokyoInstant), '2026-07-17');

  const expectedWindow = {
    timeZone: ASODELTOL_TIME_ZONE,
    startLocalDate: '2026-07-17',
    endLocalDate: '2026-07-31',
    businessDays: 8,
    includeStart: false,
    holidays: SYNTHETIC_HOLIDAYS,
    calendarDaysElapsed: 14,
  };

  assert.deepEqual(
    calculateBusinessDayWindow({
      startInstant: bogotaInstant,
      businessDays: 8,
      includeStart: false,
      holidays: SYNTHETIC_HOLIDAYS,
    }),
    expectedWindow,
  );
  assert.deepEqual(
    calculateBusinessDayWindow({
      startInstant: cairoInstant,
      businessDays: 8,
      includeStart: false,
      holidays: SYNTHETIC_HOLIDAYS,
    }),
    expectedWindow,
  );
});

test('el resultado no depende de la zona horaria del host', () => {
  const hostTimezones = ['UTC', 'America/New_York', 'Asia/Tokyo', 'Europe/Madrid', 'America/Bogota'];
  const expectedEnd = '2026-07-31';

  for (const tz of hostTimezones) {
    const script = `
      import { calculateBusinessDayWindow } from './scripts/academic/business-day-window.mjs';
      const res = calculateBusinessDayWindow({
        startInstant: '2026-07-18T02:30:00Z',
        businessDays: 8,
        includeStart: false,
        holidays: ['2026-07-20', '2026-07-27'],
      });
      process.stdout.write(res.endLocalDate);
    `;
    const result = execFileSync(process.execPath, ['--input-type=module', '-e', script], {
      env: { ...process.env, TZ: tz },
      encoding: 'utf8',
    });
    assert.equal(result, expectedEnd, `Falló para TZ=${tz}`);
  }
});

