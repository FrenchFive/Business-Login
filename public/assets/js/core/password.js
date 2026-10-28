/**
 * Password rules and a live strength meter.
 * The rules mirror App\Auth\PasswordPolicy — the server stays the authority.
 */

export const MIN_LENGTH = 8;

const RULES = [
    { test: (value) => [...value].length >= MIN_LENGTH, message: `Use at least ${MIN_LENGTH} characters.` },
    { test: (value) => /\p{L}/u.test(value), message: 'Include at least one letter.' },
    { test: (value) => /\d/.test(value), message: 'Include at least one number.' },
];

const LABELS = ['Use 8+ characters with letters and numbers', 'Weak', 'Fair', 'Good', 'Strong'];

/** First unmet rule, or null when the password satisfies the policy. */
export function policyError(value) {
    return RULES.find((rule) => !rule.test(value))?.message ?? null;
}

/** 0 (empty) to 4 (strong). */
export function strength(value) {
    if (!value) return 0;

    const length = [...value].length;
    const variety = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter((pattern) => pattern.test(value)).length;
    let score = 1;

    if (length >= 10 && variety >= 2) score++;
    if (length >= 12 && variety >= 3) score++;
    if (length >= 16 || (length >= 12 && variety === 4)) score++;
    if (/^(.)\1+$/.test(value) || policyError(value)) score = 1;

    return Math.min(score, 4);
}

/** Connects an <input> to its `.strength[data-strength-for]` meter. */
export function bindStrengthMeter(input) {
    const meter = document.querySelector(`[data-strength-for="${input.id}"]`);
    if (!meter) return;

    const label = meter.querySelector('.strength__label');
    const update = () => {
        const score = strength(input.value);
        meter.dataset.score = String(score);
        label.textContent = score > 1 || !input.value ? LABELS[score] : (policyError(input.value) ?? LABELS[1]);
    };

    input.addEventListener('input', update);
    update();
}
