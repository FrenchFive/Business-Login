/**
 * DOM helpers, including a tiny auto-escaping `html` template tag so data from
 * the API can never inject markup (XSS-safe rendering without a framework).
 */

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

class SafeHtml {
    constructor(value) {
        this.value = value;
    }

    toString() {
        return this.value;
    }
}

/** Marks a string as trusted markup (only for strings we built ourselves). */
export const raw = (value) => new SafeHtml(String(value));

export const escape = (value) =>
    value === null || value === undefined || value === false ? '' : String(value).replace(/[&<>"']/g, (c) => ESCAPES[c]);

const stringify = (value) => {
    if (value instanceof SafeHtml) return value.value;
    if (Array.isArray(value)) return value.map(stringify).join('');
    return escape(value);
};

/** html`<p>${userInput}</p>` — every interpolation is escaped unless wrapped in raw()/html``. */
export const html = (strings, ...values) =>
    raw(strings.reduce((out, chunk, i) => out + stringify(values[i - 1]) + chunk));

export const icon = (name, className = '') =>
    raw(`<svg class="icon ${escape(className)}" aria-hidden="true" focusable="false"><use href="#i-${escape(name)}"></use></svg>`);

/** Replaces an element's content with markup built by `html` (arrays are concatenated). */
export const render = (element, markup) => {
    element.innerHTML = stringify(markup);
};
