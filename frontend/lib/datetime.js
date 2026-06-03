export const ECUADOR_TIME_ZONE = "America/Guayaquil";
export const ECUADOR_LOCALE = "es-EC";

const ECUADOR_OFFSET = "-05:00";

function hasExplicitTimeZone(value) {
  return /(?:z|[+-]\d{2}:?\d{2})$/i.test(value);
}

function normalizeDateValue(value) {
  if (!value) return null;
  if (value instanceof Date || typeof value === "number") return value;

  const raw = String(value).trim();
  if (!raw) return null;

  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    return `${raw}T00:00:00${ECUADOR_OFFSET}`;
  }

  const normalized = raw.includes(" ") ? raw.replace(" ", "T") : raw;

  if (/^\d{4}-\d{2}-\d{2}T/.test(normalized) && !hasExplicitTimeZone(normalized)) {
    return `${normalized}${ECUADOR_OFFSET}`;
  }

  return normalized;
}

export function parseEcuadorDate(value) {
  const date = new Date(normalizeDateValue(value));
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatInEcuador(value, options, fallback = "Sin fecha") {
  const date = parseEcuadorDate(value);
  if (!date) return fallback;

  return new Intl.DateTimeFormat(ECUADOR_LOCALE, {
    timeZone: ECUADOR_TIME_ZONE,
    ...options,
  }).format(date);
}

export function formatEcuadorDateTime(value, fallback = "Sin fecha") {
  return formatInEcuador(
    value,
    {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    },
    fallback
  );
}

export function formatEcuadorDateOnly(value, fallback = "Sin fecha") {
  return formatInEcuador(
    value,
    {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    },
    fallback
  );
}

export function formatEcuadorMediumDateTime(value, fallback = "-") {
  return formatInEcuador(
    value,
    {
      dateStyle: "medium",
      timeStyle: "short",
    },
    fallback
  );
}

export function formatEcuadorMediumDate(value, fallback = "-") {
  return formatInEcuador(
    value,
    {
      dateStyle: "medium",
    },
    fallback
  );
}
