/**
 * Sign-in, sign-up and password recovery pages.
 * Each form posts JSON to the API (see core/ui.js → ajaxForm) and reacts in place.
 */

import { $, html, icon, render } from './core/dom.js';
import { policyError } from './core/password.js';
import { ajaxForm, cooldown, enhancePasswordFields, shake, showFormAlert } from './core/ui.js';

const newPasswordRules = (data) => {
    const error = policyError(data.password ?? '');
    return error ? { password: error } : {};
};

const pages = {
    login() {
        const form = $('[data-form="login"]');
        const fill = $('[data-fill-demo]');

        fill?.addEventListener('click', () => {
            form.elements.email.value = fill.dataset.email;
            form.elements.password.value = fill.dataset.password;
            render(fill, html`${icon('check')} Filled`);
            form.querySelector('[type="submit"]').focus();
        });

        ajaxForm(form, {
            onError(error) {
                if (error.code === 'too_many_attempts') {
                    showFormAlert(form, error.message);
                    cooldown(form.querySelector('[type="submit"]'), error.retryAfter ?? 60);
                    return true;
                }

                if (error.code === 'invalid_credentials') {
                    showFormAlert(form, error.message);
                    shake(form, ['password']);
                    form.elements.password.select();
                    return true;
                }

                return false;
            },
        });
    },

    register() {
        ajaxForm($('[data-form="register"]'), { validate: newPasswordRules });
    },

    'forgot-password'() {
        const form = $('[data-form="forgot-password"]');
        const request = $('[data-step="request"]');
        const sent = $('[data-step="sent"]');

        ajaxForm(form, {
            onError(error) {
                if (error.code === 'too_many_attempts') {
                    showFormAlert(form, error.message);
                    cooldown(form.querySelector('[type="submit"]'), error.retryAfter ?? 60);
                    return true;
                }
                return false;
            },
            onSuccess(payload) {
                $('[data-sent-message]').textContent = payload.message;

                const demo = payload.demo;
                $('[data-demo-mail]').hidden = !demo;

                if (demo) {
                    $('[data-demo-mail-file]').textContent = demo.mail_file;
                    $('[data-demo-mail-link]').href = demo.reset_url;
                }

                request.hidden = true;
                sent.hidden = false;
            },
        });

        $('[data-restart]').addEventListener('click', () => {
            sent.hidden = true;
            request.hidden = false;
            form.reset();
            form.elements.email.focus();
        });
    },

    'reset-password'() {
        const form = $('[data-form="reset-password"]');
        if (!form) return; // the link was invalid: nothing to wire up

        ajaxForm(form, {
            validate(data) {
                const errors = newPasswordRules(data);
                if (data.password && data.password !== data.password_confirmation) {
                    errors.password_confirmation = 'The two passwords do not match.';
                }
                return errors;
            },
            onError(error) {
                if (error.code === 'invalid_token') {
                    $('[data-step="reset"]').hidden = true;
                    $('[data-step="invalid"]').hidden = false;
                    return true;
                }
                return false;
            },
        });
    },
};

enhancePasswordFields();
pages[document.body.dataset.page]?.();
