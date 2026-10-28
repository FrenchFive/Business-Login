/**
 * Reusable UI behaviour: toasts, button loading states, inline field errors,
 * password visibility, Caps Lock warning and the generic AJAX form handler.
 */

import { ApiError, api } from './http.js';
import { $, $$, html, icon, render } from './dom.js';
import { bindStrengthMeter } from './password.js';

/* ----- Toasts ------------------------------------------------------------ */

const TOAST_ICONS = { success: 'circle-check', error: 'circle-alert', warning: 'triangle-alert', info: 'info' };

export function toast({ type = 'info', title, message = '', timeout = 5000 }) {
    const region = $('[data-toasts]');
    if (!region) return;

    const element = document.createElement('div');
    element.className = `toast toast--${type}`;
    element.setAttribute('role', type === 'error' ? 'alert' : 'status');
    render(element, html`
        <span class="toast__icon">${icon(TOAST_ICONS[type] ?? 'info')}</span>
        <div class="toast__body">
            <p class="toast__title">${title}</p>
            ${message ? html`<p class="toast__message">${message}</p>` : ''}
        </div>
        <button class="toast__close" type="button" aria-label="Dismiss notification">${icon('x')}</button>
    `);

    const dismiss = () => {
        element.classList.add('is-leaving');
        element.addEventListener('animationend', () => element.remove(), { once: true });
    };

    element.querySelector('.toast__close').addEventListener('click', dismiss);
    region.append(element);

    if (timeout) setTimeout(dismiss, timeout);
}

/* ----- Buttons ----------------------------------------------------------- */

export function setBusy(button, busy) {
    if (!button) return;
    button.classList.toggle('is-loading', busy);
    button.disabled = busy;
    button.setAttribute('aria-busy', String(busy));
}

/** Disables a button with a live "Try again in mm:ss" countdown (used for HTTP 429). */
export function cooldown(button, seconds) {
    const label = button.querySelector('.button__label');
    const original = label.textContent;
    const end = Date.now() + seconds * 1000;

    button.disabled = true;

    const tick = () => {
        const left = Math.max(0, Math.ceil((end - Date.now()) / 1000));

        if (left === 0) {
            label.textContent = original;
            button.disabled = false;
            return;
        }

        const minutes = Math.floor(left / 60);
        label.textContent = `Try again in ${minutes}:${String(left % 60).padStart(2, '0')}`;
        setTimeout(tick, 1000);
    };

    tick();
}

/* ----- Field errors ------------------------------------------------------ */

export function clearErrors(form) {
    $$('.field__error', form).forEach((error) => {
        error.hidden = true;
        error.textContent = '';
    });
    $$('[aria-invalid]', form).forEach((input) => input.removeAttribute('aria-invalid'));

    const alert = $('[data-form-alert]', form);
    if (alert) alert.hidden = true;
}

export function showErrors(form, fields) {
    let first = null;

    for (const [name, message] of Object.entries(fields)) {
        const input = form.elements.namedItem(name);
        const field = input?.closest?.('.field');
        const error = field ? $('.field__error', field) : null;

        if (!error) continue;

        input.setAttribute('aria-invalid', 'true');
        render(error, html`${icon('circle-alert')} ${message}`);
        error.hidden = false;
        first ??= input;
    }

    first?.focus();
}

export function showFormAlert(form, message) {
    const alert = $('[data-form-alert]', form);

    if (!alert) {
        toast({ type: 'error', title: 'Something went wrong', message });
        return;
    }

    render(alert, html`${icon('circle-alert')}<span>${message}</span>`);
    alert.hidden = false;
}

export function shake(form, names) {
    for (const name of names) {
        const field = form.elements.namedItem(name)?.closest('.field');
        if (!field) continue;
        field.classList.remove('is-shaking');
        void field.offsetWidth; // restart the animation
        field.classList.add('is-shaking');
    }
}

/* ----- Client-side validation -------------------------------------------- */

function labelFor(input) {
    return input.closest('.field')?.querySelector('.field__label')?.textContent.trim() ?? 'This field';
}

/** Mirrors the server's required/e-mail checks for instant feedback. */
export function validateRequired(form) {
    const errors = {};

    for (const input of $$('input[required]', form)) {
        const value = input.type === 'password' ? input.value : input.value.trim();

        if (value === '') {
            errors[input.name] = `${labelFor(input)} is required.`;
        } else if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            errors[input.name] = 'Enter a valid e-mail address, like name@company.com.';
        }
    }

    return errors;
}

/** Form → plain object. Checkboxes become booleans; read-only fields are skipped. */
export function serialize(form) {
    const data = {};

    for (const input of $$('input[name]', form)) {
        if (input.readOnly) continue;
        data[input.name] = input.type === 'checkbox' ? input.checked : input.value;
    }

    return data;
}

/* ----- Password fields --------------------------------------------------- */

export function enhancePasswordFields(root = document) {
    for (const toggle of $$('[data-toggle-password]', root)) {
        const input = document.getElementById(toggle.getAttribute('aria-controls'));

        toggle.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(visible));
            toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            input.focus({ preventScroll: true });
        });
    }

    for (const input of $$('input[type="password"]', root)) {
        const warning = input.closest('.field')?.querySelector('.field__caps');

        if (warning) {
            const check = (event) => {
                if (typeof event.getModifierState === 'function') {
                    warning.hidden = !event.getModifierState('CapsLock');
                }
            };
            input.addEventListener('keydown', check);
            input.addEventListener('keyup', check);
            input.addEventListener('blur', () => (warning.hidden = true));
        }

        bindStrengthMeter(input);
    }
}

/* ----- AJAX forms -------------------------------------------------------- */

/**
 * Submits a form to its `action` URL as JSON, with loading state, inline errors
 * and toasts. If the response contains `redirect`, the browser follows it.
 *
 * @param {HTMLFormElement} form
 * @param {object} options
 * @param {(data: object, form: HTMLFormElement) => object} [options.validate] extra client-side checks
 * @param {(payload: object, data: object) => void} [options.onSuccess]
 * @param {(error: ApiError) => boolean} [options.onError] return true when handled
 */
export function ajaxForm(form, { validate, onSuccess, onError } = {}) {
    const button = form.querySelector('[type="submit"]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (button?.disabled) return;

        clearErrors(form);
        const data = serialize(form);
        const errors = { ...(validate?.(data, form) ?? {}), ...validateRequired(form) };

        if (Object.keys(errors).length > 0) {
            showErrors(form, errors);
            shake(form, Object.keys(errors));
            return;
        }

        setBusy(button, true);
        let payload;

        try {
            payload = await api.post(form.getAttribute('action'), data);
        } catch (error) {
            setBusy(button, false);
            if (!(error instanceof ApiError)) throw error;
            if (onError?.(error)) return;

            if (Object.keys(error.fields).length > 0) {
                showErrors(form, error.fields);
                shake(form, Object.keys(error.fields));
            } else if (error.code === 'csrf_token_mismatch') {
                toast({ type: 'warning', title: 'This page has expired', message: 'Refreshing it for you…' });
                setTimeout(() => window.location.reload(), 1200);
            } else {
                showFormAlert(form, error.message);
            }

            return;
        }

        if (payload.redirect) {
            // Keep the spinner while the browser navigates away.
            if (payload.message) toast({ type: 'success', title: payload.message });
            setTimeout(() => window.location.assign(payload.redirect), 450);
        } else {
            setBusy(button, false);
        }

        onSuccess?.(payload, data);
    });
}
