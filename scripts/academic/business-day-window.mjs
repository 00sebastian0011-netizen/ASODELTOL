const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

export const ASODELTOL_TIME_ZONE = 'America/Bogota';

function assertDateString(value, label) {
  if (typeof value !== 'string' || !DATE_PATTERN.test(value)) {
    throw new TypeError(`${label} must use YYYY-MM-DD.`);
  }

  const parsed = new Date(`${value}T00:00:00Z`);
  if (Number.isNaN(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== value) {
    throw new RangeError(`${label} is not a valid calendar date.`);
  }
}

function addCalendarDays(value, days) {
  const parsed = new Date(`${value}T00:00:00Z`);
  parsed.setUTCDate(parsed.getUTCDate() + days);
  return parsed.toISOString().slice(0, 10);
}

function calendarDayDifference(start, end) {
  const startTime = Date.parse(`${start}T00:00:00Z`);
  const endTime = Date.parse(`${end}T00:00:00Z`);
  return Math.round((endTime - startTime) / 86_400_000);
}

function assertTimeZone(timeZone) {
  if (timeZone !== ASODELTOL_TIME_ZONE) {
    throw new RangeError(`Only ${ASODELTOL_TIME_ZONE} is valid for this AF8 model.`);
  }
}

export function localDateFromInstant(instant, timeZone = ASODELTOL_TIME_ZONE) {
  assertTimeZone(timeZone);
  const parsed = new Date(instant);
  if (Number.isNaN(parsed.getTime())) {
    throw new RangeError('startInstant must be a valid ISO-8601 instant.');
  }

  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(parsed);
  const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
  return `${values.year}-${values.month}-${values.day}`;
}

export function isBusinessDate(localDate, holidays = []) {
  assertDateString(localDate, 'localDate');
  const holidaySet = new Set(holidays);
  for (const holiday of holidaySet) {
    assertDateString(holiday, 'holiday');
  }

  const day = new Date(`${localDate}T00:00:00Z`).getUTCDay();
  return day !== 0 && day !== 6 && !holidaySet.has(localDate);
}

/**
 * Calculate a synthetic business-day window without choosing an institutional policy.
 *
 * The caller must provide the holiday dates and must explicitly decide whether the
 * trigger date counts. Neither choice is treated as the approved AF8 rule.
 */
export function calculateBusinessDayWindow({
  startInstant,
  startLocalDate,
  businessDays = 8,
  includeStart,
  holidays = [],
  timeZone = ASODELTOL_TIME_ZONE,
}) {
  assertTimeZone(timeZone);
  if ((startInstant === undefined) === (startLocalDate === undefined)) {
    throw new TypeError('Provide exactly one of startInstant or startLocalDate.');
  }
  if (!Number.isInteger(businessDays) || businessDays < 1) {
    throw new RangeError('businessDays must be a positive integer.');
  }
  if (typeof includeStart !== 'boolean') {
    throw new TypeError('includeStart must be explicitly true or false.');
  }

  const start = startLocalDate ?? localDateFromInstant(startInstant, timeZone);
  assertDateString(start, 'startLocalDate');
  const normalizedHolidays = [...new Set(holidays)].sort();
  for (const holiday of normalizedHolidays) {
    assertDateString(holiday, 'holiday');
  }

  let current = start;
  let counted = includeStart && isBusinessDate(current, normalizedHolidays) ? 1 : 0;
  while (counted < businessDays) {
    current = addCalendarDays(current, 1);
    if (isBusinessDate(current, normalizedHolidays)) {
      counted += 1;
    }
  }

  return Object.freeze({
    timeZone,
    startLocalDate: start,
    endLocalDate: current,
    businessDays,
    includeStart,
    holidays: Object.freeze(normalizedHolidays),
    calendarDaysElapsed: calendarDayDifference(start, current),
  });
}
