/** Locale-aware date helpers built on Intl (no libraries). */

const relative = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
const dateFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });
const dateTimeFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

const UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

export function timeAgo(iso) {
    if (!iso) return 'Never';

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);

    if (Math.abs(seconds) < 45) return 'just now';

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return relative.format(Math.round(seconds / 60), 'minute');
}

export const formatDate = (iso) => (iso ? dateFormat.format(new Date(iso)) : '—');
export const formatDateTime = (iso) => (iso ? dateTimeFormat.format(new Date(iso)) : '—');

export function greeting(date = new Date()) {
    const hour = date.getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
}

export const plural = (count, word, pluralWord = `${word}s`) => `${count} ${count === 1 ? word : pluralWord}`;
