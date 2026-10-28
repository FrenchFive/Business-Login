/**
 * Dashboard: hash-routed tabs whose data is loaded from the JSON API on demand.
 *
 *   #overview  → GET /api/me
 *   #security  → GET /api/account/security   (+ change password, forget devices)
 *   #team      → GET /api/team
 *   #profile   → POST /api/account/profile
 */

import { ApiError, api } from './core/http.js';
import { $, $$, html, icon, render } from './core/dom.js';
import { formatDate, formatDateTime, greeting, plural, timeAgo } from './core/format.js';
import { policyError } from './core/password.js';
import { ajaxForm, enhancePasswordFields, setBusy, toast } from './core/ui.js';

const EVENT_ICONS = {
    account_created: 'sparkles',
    login: 'log-in',
    login_remembered: 'fingerprint',
    login_failed: 'shield-alert',
    logout: 'log-out',
    password_changed: 'key-round',
    password_reset: 'key-round',
    password_reset_requested: 'mail',
    profile_updated: 'user-pen',
    devices_forgotten: 'log-out',
};

const DEVICE_ICONS = { desktop: 'monitor', mobile: 'smartphone', tablet: 'tablet', other: 'terminal' };

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const appName = document.title.split(' · ').pop();

/** GET with session handling: a 401 means the session expired → back to sign-in. */
async function load(url) {
    try {
        return await api.get(url);
    } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
            window.location.assign('/login?status=expired');
            return new Promise(() => {}); // never resolves: we are navigating away
        }
        throw error;
    }
}

/* ----- Shared renderers -------------------------------------------------- */

const timeTag = (iso) => html`<time datetime="${iso}" title="${formatDateTime(iso)}">${timeAgo(iso)}</time>`;

const eventIcon = (event) =>
    html`<span class="event-icon tone-${event.tone}">${icon(EVENT_ICONS[event.event] ?? 'activity')}</span>`;

const emptyState = (iconName, title, text) =>
    html`<div class="empty">${icon(iconName)}<strong>${title}</strong><span>${text}</span></div>`;

function countUp(element) {
    const target = Number(element.dataset.count);

    if (reducedMotion || target === 0) {
        element.textContent = String(target);
        return;
    }

    const start = performance.now();
    const step = (now) => {
        const progress = Math.min(1, (now - start) / 700);
        element.textContent = String(Math.round(target * (1 - (1 - progress) ** 3)));
        if (progress < 1) requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
}

function updateUser(user) {
    $$('[data-user-name]').forEach((el) => (el.textContent = user.name));
    $$('[data-user-first-name]').forEach((el) => (el.textContent = user.first_name));
    $$('[data-user-initials]').forEach((el) => (el.textContent = user.initials));
    $$('[data-user-title]').forEach((el) => (el.textContent = user.job_title ?? 'No job title yet'));
}

/* ----- Overview ---------------------------------------------------------- */

async function loadOverview() {
    const { organization, stats, previous_sign_in: previous, session, recent_activity: activity } = await load('/api/me');

    render($('[data-previous-sign-in]'), previous
        ? html`${icon('history')}<div><strong>Last sign-in ${timeAgo(previous.at)}</strong><span>${previous.device} · ${previous.ip}</span></div>`
        : html`${icon('sparkles')}<div><strong>First sign-in on this account</strong><span>Welcome aboard!</span></div>`);

    const cards = [
        { icon: 'log-in', tone: 'success', label: 'Sign-ins', value: stats.sign_ins_30d, hint: 'Last 30 days' },
        {
            icon: 'shield-alert',
            tone: stats.failed_30d > 0 ? 'danger' : 'neutral',
            label: 'Failed attempts',
            value: stats.failed_30d,
            hint: stats.failed_30d > 0 ? 'Check the Security tab' : 'Nothing suspicious',
        },
        { icon: 'users', tone: 'info', label: 'Team members', value: organization.member_count, hint: organization.name },
        {
            icon: 'fingerprint',
            tone: 'warning',
            label: 'Remembered devices',
            value: stats.remembered_devices,
            hint: stats.remembered_devices > 0 ? `Signed in for up to ${plural(session.remember_days, 'day')}` : 'Session-only sign-ins',
        },
    ];

    render($('[data-stats]'), cards.map((card) => html`
        <article class="card stat tone-${card.tone}">
            <span class="stat__icon">${icon(card.icon)}</span>
            <span class="stat__label">${card.label}</span>
            <span class="stat__value" data-count="${card.value}">0</span>
            <span class="stat__hint">${card.hint}</span>
        </article>`));
    $$('[data-count]').forEach(countUp);

    render($('[data-recent-activity]'), activity.length === 0
        ? html`<li>${emptyState('activity', 'No activity yet', 'Security events will appear here.')}</li>`
        : activity.map((event) => html`
            <li class="activity__item">
                ${eventIcon(event)}
                <div class="activity__text"><strong>${event.label}</strong><span>${event.device} · ${event.ip}</span></div>
                <span class="activity__time">${timeTag(event.at)}</span>
            </li>`));

    const rows = [
        ['clock', 'Signed in', session.signed_in_at ? formatDateTime(session.signed_in_at) : '—'],
        [DEVICE_ICONS[session.device_type] ?? 'monitor', 'Device', session.device],
        ['globe', 'IP address', session.ip],
        ['fingerprint', 'Remembered', session.remembered ? `Yes — ${plural(session.remember_days, 'day')}` : 'No — this session only'],
        ['history', 'Idle timeout', plural(session.idle_timeout_minutes, 'minute')],
    ];

    render($('[data-session]'), rows.map(([iconName, label, value]) =>
        html`<div class="details__row"><dt>${icon(iconName)}${label}</dt><dd>${value}</dd></div>`));
}

/* ----- Security ---------------------------------------------------------- */

async function loadSecurity() {
    const { events, devices, password_changed_at: changedAt } = await load('/api/account/security');

    $('[data-password-changed]').textContent = changedAt ? `Last changed ${timeAgo(changedAt)}` : 'Use a long, unique password';

    render($('[data-devices]'), devices.length === 0
        ? html`<li>${emptyState('fingerprint', 'No remembered devices', 'Tick “Keep me signed in” when signing in to remember a browser.')}</li>`
        : devices.map((device) => html`
            <li class="devices__item">
                <span class="event-icon tone-info">${icon(DEVICE_ICONS[device.device_type] ?? 'monitor')}</span>
                <div class="devices__text">
                    <strong>${device.device} ${device.current ? html`<span class="you-tag">This browser</span>` : ''}</strong>
                    <span>${device.ip} · added ${timeAgo(device.created_at)} · expires ${formatDate(device.expires_at)}</span>
                </div>
            </li>`));
    $('[data-forget-devices]').disabled = devices.length === 0;

    render($('[data-events]'), events.length === 0
        ? html`<tr><td colspan="4">${emptyState('activity', 'No events yet', 'Your audit trail is empty.')}</td></tr>`
        : events.map((event) => html`
            <tr>
                <td><div class="cell-event">${eventIcon(event)}<strong>${event.label}</strong></div></td>
                <td class="cell-muted cell-nowrap">${event.device}</td>
                <td class="cell-mono hide-sm">${event.ip}</td>
                <td class="cell-muted cell-nowrap">${timeTag(event.at)}</td>
            </tr>`));
}

function setUpSecurityActions() {
    const form = $('[data-form="password"]');

    ajaxForm(form, {
        validate(data) {
            const errors = {};
            const problem = policyError(data.password ?? '');
            if (problem) errors.password = problem;
            else if (data.password !== data.password_confirmation) errors.password_confirmation = 'The two passwords do not match.';
            return errors;
        },
        onSuccess(payload) {
            toast({ type: 'success', title: 'Password updated', message: payload.message });
            form.reset();
            form.querySelectorAll('input').forEach((input) => input.dispatchEvent(new Event('input')));
            loadSecurity();
        },
    });

    // Two-step "are you sure?" button instead of a blocking confirm() dialog.
    const forget = $('[data-forget-devices]');
    const label = forget.querySelector('.button__label');
    const idleLabel = label.textContent;
    let timer;

    const disarm = () => {
        clearTimeout(timer);
        forget.classList.remove('is-confirming');
        label.textContent = idleLabel;
    };

    forget.addEventListener('click', async () => {
        if (!forget.classList.contains('is-confirming')) {
            forget.classList.add('is-confirming');
            label.textContent = 'Click again to confirm';
            timer = setTimeout(disarm, 4000);
            return;
        }

        disarm();
        setBusy(forget, true);

        try {
            const payload = await api.post('/api/account/devices/forget');
            toast({ type: 'success', title: 'Devices signed out', message: payload.message });
            await loadSecurity();
        } catch (error) {
            toast({ type: 'error', title: 'Could not sign devices out', message: error.message });
        } finally {
            setBusy(forget, false);
        }
    });

    $('[data-refresh="security"]').addEventListener('click', () => runTab('security'));
}

/* ----- Team -------------------------------------------------------------- */

async function loadTeam() {
    const { members } = await load('/api/team');

    $('[data-team-count]').textContent = `${plural(members.length, 'member')} in this workspace`;

    render($('[data-team]'), members.map((member) => html`
        <tr>
            <td>
                <div class="cell-member">
                    <span class="avatar" data-hue="${member.avatar_hue}">${member.initials}</span>
                    <div>
                        <strong>${member.name} ${member.is_you ? html`<span class="you-tag">You</span>` : ''}</strong>
                        <span>${member.email}</span>
                    </div>
                </div>
            </td>
            <td><span class="badge badge--${member.role}">${member.role_label}</span></td>
            <td class="cell-muted hide-sm">${member.job_title ?? '—'}</td>
            <td class="cell-muted cell-nowrap">${member.last_login_at ? timeTag(member.last_login_at) : 'Never'}</td>
        </tr>`));
}

/* ----- Profile ----------------------------------------------------------- */

function setUpProfile() {
    ajaxForm($('[data-form="profile"]'), {
        onSuccess(payload) {
            updateUser(payload.user);
            toast({ type: 'success', title: payload.message });
        },
    });
}

/* ----- Tabs (hash routing) ----------------------------------------------- */

const TABS = { overview: loadOverview, security: loadSecurity, team: loadTeam, profile: null };

async function runTab(name) {
    try {
        await TABS[name]?.();
    } catch (error) {
        toast({ type: 'error', title: 'Could not load this section', message: error.message });
    }
}

function showTab(name) {
    const tab = name in TABS ? name : 'overview';
    const section = $(`[data-tab="${tab}"]`);

    $$('[data-tab]').forEach((element) => (element.hidden = element !== section));
    $$('[data-tab-link]').forEach((link) => {
        const active = link.dataset.tabLink === tab;
        link.classList.toggle('is-active', active);
        if (active) {
            link.setAttribute('aria-current', 'page');
            link.scrollIntoView({ block: 'nearest', inline: 'nearest' }); // horizontal tab bar on phones
        } else {
            link.removeAttribute('aria-current');
        }
    });

    $('[data-tab-title]').textContent = section.dataset.title;
    document.title = `${section.dataset.title} · ${appName}`;
    runTab(tab);
}

/* ----- Boot -------------------------------------------------------------- */

$('[data-greeting]').textContent = `${greeting()} · ${new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date())}`;
$$('[data-format-date]').forEach((el) => (el.textContent = formatDate(el.dataset.formatDate)));

$('[data-logout]').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    setBusy(button, true);

    try {
        const payload = await api.post('/api/auth/logout');
        window.location.assign(payload.redirect);
    } catch (error) {
        setBusy(button, false);

        if (error instanceof ApiError && error.status === 401) {
            window.location.assign('/login?status=expired');
        } else {
            toast({ type: 'error', title: 'Could not sign you out', message: error.message });
        }
    }
});

enhancePasswordFields();
setUpSecurityActions();
setUpProfile();

window.addEventListener('hashchange', () => showTab(window.location.hash.slice(1)));
showTab(window.location.hash.slice(1));
