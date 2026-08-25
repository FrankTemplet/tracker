/**
 * Duration helpers for lead aging.
 *
 * There is no date library in this project, so these are plain arithmetic on a
 * number of hours. Keep them dependency-free.
 */

export type AgingSeverity = 'ok' | 'warn' | 'late';

/** Hours after which an untouched lead is considered slow, then overdue. */
export const AGING_WARN_HOURS = 24;
export const AGING_LATE_HOURS = 72;

/**
 * Render a number of hours as a short human duration: "45m", "9h 4m", "3d 4h".
 * Only the two most significant units are shown — "3d 4h" reads better than
 * "3d 4h 12m" on a metric this coarse.
 */
export function formatDuration(hours: number | null | undefined): string {
    if (hours === null || hours === undefined || Number.isNaN(hours)) {
return '—';
}

    if (hours < 0) {
return '—';
}

    const totalMinutes = Math.round(hours * 60);

    if (totalMinutes < 1) {
return '< 1m';
}

    const days = Math.floor(totalMinutes / 1440);
    const remHours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;

    if (days > 0) {
return remHours > 0 ? `${days}d ${remHours}h` : `${days}d`;
}

    if (remHours > 0) {
return minutes > 0 ? `${remHours}h ${minutes}m` : `${remHours}h`;
}

    return `${minutes}m`;
}

/** Bucket a duration so the UI can colour it consistently. */
export function agingSeverity(hours: number | null | undefined): AgingSeverity {
    if (hours === null || hours === undefined || Number.isNaN(hours)) {
return 'ok';
}

    if (hours >= AGING_LATE_HOURS) {
return 'late';
}

    if (hours >= AGING_WARN_HOURS) {
return 'warn';
}

    return 'ok';
}

/** Tailwind text colour per severity, matching the badge palette used elsewhere. */
export const AGING_TEXT_CLASS: Record<AgingSeverity, string> = {
    ok: 'text-emerald-600 dark:text-emerald-400',
    warn: 'text-amber-600 dark:text-amber-400',
    late: 'text-red-600 dark:text-red-400',
};

/** Tailwind badge classes per severity. */
export const AGING_BADGE_CLASS: Record<AgingSeverity, string> = {
    ok: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
    warn: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800',
    late: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800',
};
