/**
 * Date, Time & Number Formatters
 * Built with robust timezone-safety best practices:
 * 1. Date-only strings ("YYYY-MM-DD" or "YYYY/MM/DD") are parsed without UTC-midnight shifts.
 * 2. SQL timestamps ("YYYY-MM-DD HH:mm:ss") are normalized to ISO-8601 UTC before parsing.
 * 3. Dates are formatted with '-' separators and 12-hour am/pm format:
 *    e.g. 2026-11-05 11:47:18 am
 */

/**
 * Safely parse any date value (ISO string, SQL string, timestamp number, or Date object)
 * preventing cross-browser and timezone-shift anomalies.
 */
export function parseDate(val) {
    if (!val) return null;
    if (val instanceof Date) {
        return isNaN(val.getTime()) ? null : val;
    }
    if (typeof val === 'number') {
        const d = new Date(val);
        return isNaN(d.getTime()) ? null : d;
    }
    if (typeof val !== 'string') return null;

    const trimmed = val.trim();
    if (!trimmed) return null;

    // 1. Check if date-only: "YYYY-MM-DD" or "YYYY/MM/DD"
    const dateOnlyMatch = /^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/.exec(trimmed);
    if (dateOnlyMatch) {
        const [, y, m, d] = dateOnlyMatch;
        // Construct as local midnight to avoid UTC-midnight timezone shift
        return new Date(Number(y), Number(m) - 1, Number(d));
    }

    // 2. Check if SQL datetime string: "YYYY-MM-DD HH:mm:ss" or "YYYY/MM/DD HH:mm:ss"
    // Normalize space to 'T' for cross-browser Safari/WebKit compatibility
    let normalized = trimmed.replace(' ', 'T');

    // If it has date and time but lacks a timezone indicator, treat as UTC (server storage)
    if (
        /^\d{4}[-/]\d{1,2}[-/]\d{1,2}T\d{1,2}:\d{1,2}/.test(normalized) &&
        !normalized.endsWith('Z') &&
        !/[+-]\d{2}(?::?\d{2})?$/.test(normalized)
    ) {
        normalized += 'Z';
    }

    // Replace slashes with dashes in the ISO date portion if present
    normalized = normalized.replace(/^(\d{4})\/(\d{1,2})\/(\d{1,2})/, '$1-$2-$3');

    const parsed = new Date(normalized);
    return isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Format a date & time value to: YYYY-MM-DD hh:mm:ss am/pm
 * Example: 2026-11-05 11:47:18 am
 */
export function formatDateTime(val) {
    if (!val) return '—';

    // If pure date-only string with no time, format as date
    if (typeof val === 'string' && /^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$/.test(val.trim())) {
        return formatDate(val);
    }

    const d = parseDate(val);
    if (!d) return String(val);

    const pad = (n) => String(n).padStart(2, '0');
    const year = d.getFullYear();
    const month = pad(d.getMonth() + 1);
    const day = pad(d.getDate());

    const rawHours = d.getHours();
    const hours12 = pad(rawHours % 12 || 12);
    const minutes = pad(d.getMinutes());
    const seconds = pad(d.getSeconds());
    const ampm = rawHours >= 12 ? 'pm' : 'am';

    return `${year}-${month}-${day} ${hours12}:${minutes}:${seconds} ${ampm}`;
}

/**
 * Alias for formatDateTime to ensure backwards compatibility across existing components
 */
export const formatIsoDateTime = formatDateTime;

/**
 * Format date only: YYYY/MM/DD
 * Example: 2026/11/05
 * 100% immune to timezone shifts by directly extracting calendar parts for date-only strings.
 */
export function formatDate(val) {
    if (!val) return '—';

    if (typeof val === 'string') {
        const trimmed = val.trim();
        // If pure date string like "2026-11-05" or "2026/11/05", extract directly (timezone-safe bypass)
        const dateMatch = /^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/.exec(trimmed);
        if (dateMatch && !trimmed.includes('T') && !trimmed.includes(' ')) {
            const [, y, m, d] = dateMatch;
            return `${y}/${String(m).padStart(2, '0')}/${String(d).padStart(2, '0')}`;
        }
    }

    const d = parseDate(val);
    if (!d) return String(val);

    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}/${pad(d.getMonth() + 1)}/${pad(d.getDate())}`;
}

/**
 * Alias for formatDate to ensure backwards compatibility
 */
export const formatIsoDate = formatDate;

/**
 * Format time only: hh:mm:ss am/pm
 * Example: 11:47:18 am
 */
export function formatTime(val) {
    if (!val) return '—';
    const d = parseDate(val);
    if (!d) return String(val);

    const pad = (n) => String(n).padStart(2, '0');
    const rawHours = d.getHours();
    const hours12 = pad(rawHours % 12 || 12);
    const minutes = pad(d.getMinutes());
    const seconds = pad(d.getSeconds());
    const ampm = rawHours >= 12 ? 'pm' : 'am';

    return `${hours12}:${minutes}:${seconds} ${ampm}`;
}

/**
 * Format currency with 2 decimal places
 */
export function formatCurrency(amount, currency = 'KES') {
    const num = Number(amount ?? 0);
    return `${currency} ${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/**
 * Format a number with thousands separators and optional decimals
 */
export function formatNumber(amount, decimals = 2) {
    const num = Number(amount ?? 0);
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}
