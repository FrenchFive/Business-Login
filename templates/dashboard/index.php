<?php
/**
 * Dashboard shell. Each tab is filled in by `assets/js/dashboard.js` from the JSON API.
 *
 * @var App\View\View $view
 * @var App\Auth\User $user
 */
?>
<!-- Overview -->
<section class="tab" data-tab="overview" data-title="Overview" aria-labelledby="overview-title">
    <div class="hero">
        <div class="hero__text">
            <h2 class="hero__title" id="overview-title">Welcome back, <span data-user-first-name><?= e($user->firstName()) ?></span></h2>
            <p class="hero__subtitle">Here's what's happening with your <strong><?= e($user->organizationName) ?></strong> account today.</p>
        </div>
        <div class="hero__meta" data-previous-sign-in>
            <span class="skeleton skeleton--on-dark skeleton--line"></span>
        </div>
    </div>

    <div class="stats" data-stats>
        <?php for ($i = 0; $i < 4; $i++): ?>
            <article class="card stat stat--loading">
                <span class="skeleton skeleton--chip"></span>
                <span class="skeleton skeleton--line skeleton--short"></span>
                <span class="skeleton skeleton--number"></span>
            </article>
        <?php endfor; ?>
    </div>

    <div class="grid-2">
        <article class="card">
            <header class="card__header">
                <div>
                    <h3 class="card__title">Recent activity</h3>
                    <p class="card__subtitle">The latest events on your account</p>
                </div>
                <a class="link link--sm link--arrow" href="#security">View all <?= icon('chevron-right') ?></a>
            </header>
            <ul class="activity" data-recent-activity>
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <li class="activity__item"><span class="skeleton skeleton--chip"></span><span class="skeleton skeleton--line"></span></li>
                <?php endfor; ?>
            </ul>
        </article>

        <article class="card">
            <header class="card__header">
                <div>
                    <h3 class="card__title">This session</h3>
                    <p class="card__subtitle">The browser you are using right now</p>
                </div>
                <span class="badge badge--success"><span class="badge__dot"></span>Active</span>
            </header>
            <dl class="details" data-session>
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="details__row"><span class="skeleton skeleton--line skeleton--short"></span><span class="skeleton skeleton--line skeleton--short"></span></div>
                <?php endfor; ?>
            </dl>
        </article>
    </div>
</section>

<!-- Security -->
<section class="tab" data-tab="security" data-title="Security" hidden>
    <div class="grid-2 grid-2--even">
        <article class="card">
            <header class="card__header">
                <div>
                    <h3 class="card__title">Change password</h3>
                    <p class="card__subtitle" data-password-changed>Use a long, unique password</p>
                </div>
                <span class="card__icon"><?= icon('key-round') ?></span>
            </header>
            <form class="form card__body" data-form="password" action="/api/account/password" method="post" novalidate>
                <input class="visually-hidden" type="text" name="username" value="<?= e($user->email) ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly>
                <?= $view->partial('field', [
                    'name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'icon' => 'lock-keyhole',
                    'autocomplete' => 'current-password', 'required' => true,
                ]) ?>
                <?= $view->partial('field', [
                    'name' => 'password', 'id' => 'new_password', 'label' => 'New password', 'type' => 'password', 'icon' => 'lock-keyhole',
                    'autocomplete' => 'new-password', 'required' => true, 'strength' => true,
                ]) ?>
                <?= $view->partial('field', [
                    'name' => 'password_confirmation', 'label' => 'Confirm new password', 'type' => 'password', 'icon' => 'lock-keyhole',
                    'autocomplete' => 'new-password', 'required' => true,
                ]) ?>
                <div class="form__actions">
                    <p class="form__fineprint">Other remembered devices will be signed out.</p>
                    <button class="button button--primary" type="submit"><span class="button__label">Update password</span></button>
                </div>
            </form>
        </article>

        <article class="card">
            <header class="card__header">
                <div>
                    <h3 class="card__title">Remembered devices</h3>
                    <p class="card__subtitle">Browsers that stay signed in with “Keep me signed in”</p>
                </div>
                <span class="card__icon"><?= icon('fingerprint') ?></span>
            </header>
            <ul class="devices" data-devices>
                <li class="devices__item"><span class="skeleton skeleton--chip"></span><span class="skeleton skeleton--line"></span></li>
            </ul>
            <footer class="card__footer">
                <button class="button button--danger-ghost button--sm" type="button" data-forget-devices>
                    <?= icon('log-out') ?><span class="button__label">Sign out all devices</span>
                </button>
            </footer>
        </article>
    </div>

    <article class="card">
        <header class="card__header">
            <div>
                <h3 class="card__title">Sign-in activity</h3>
                <p class="card__subtitle">Your audit trail: the last 25 security events</p>
            </div>
            <button class="button button--secondary button--sm" type="button" data-refresh="security">
                <?= icon('history') ?><span class="button__label">Refresh</span>
            </button>
        </header>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Event</th><th>Device</th><th class="hide-sm">IP address</th><th>When</th></tr>
                </thead>
                <tbody data-events>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <tr><td colspan="4"><span class="skeleton skeleton--line"></span></td></tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<!-- Team -->
<section class="tab" data-tab="team" data-title="Team" hidden>
    <article class="card">
        <header class="card__header">
            <div>
                <h3 class="card__title"><?= e($user->organizationName) ?></h3>
                <p class="card__subtitle" data-team-count>Loading members…</p>
            </div>
            <span class="card__icon"><?= icon('users') ?></span>
        </header>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Member</th><th>Role</th><th class="hide-sm">Job title</th><th>Last sign-in</th></tr>
                </thead>
                <tbody data-team>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <tr><td colspan="4"><span class="skeleton skeleton--line"></span></td></tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<!-- Profile -->
<section class="tab" data-tab="profile" data-title="Profile" hidden>
    <article class="card profile">
        <div class="profile__banner"></div>
        <div class="profile__identity">
            <span class="avatar avatar--xl" data-hue="<?= $user->avatarHue() ?>" data-user-initials><?= e($user->initials()) ?></span>
            <div>
                <h3 class="profile__name" data-user-name><?= e($user->name) ?></h3>
                <p class="profile__meta">
                    <span class="badge badge--<?= e($user->role->value) ?>"><?= e($user->role->label()) ?></span>
                    <span data-user-title><?= e($user->jobTitle ?? 'No job title yet') ?></span>
                </p>
            </div>
        </div>
        <form class="form card__body profile__form" data-form="profile" action="/api/account/profile" method="post" novalidate>
            <div class="form__grid">
                <?= $view->partial('field', [
                    'name' => 'name', 'label' => 'Full name', 'icon' => 'user-round', 'value' => $user->name,
                    'autocomplete' => 'name', 'required' => true, 'maxlength' => 80,
                ]) ?>
                <?= $view->partial('field', [
                    'name' => 'job_title', 'label' => 'Job title', 'icon' => 'briefcase', 'value' => $user->jobTitle ?? '',
                    'placeholder' => 'e.g. Operations Lead', 'autocomplete' => 'organization-title', 'maxlength' => 80,
                ]) ?>
                <?= $view->partial('field', [
                    'name' => 'email', 'label' => 'Work e-mail', 'type' => 'email', 'icon' => 'mail', 'value' => $user->email,
                    'readonly' => true, 'hint' => 'Your e-mail is your sign-in. Ask a workspace owner to change it.',
                ]) ?>
                <?= $view->partial('field', [
                    'name' => 'organization', 'label' => 'Workspace', 'icon' => 'building-2', 'value' => $user->organizationName,
                    'readonly' => true,
                ]) ?>
            </div>
            <div class="form__actions">
                <p class="form__fineprint">Member since <time data-format-date="<?= e($user->createdAt) ?>"><?= e(substr($user->createdAt, 0, 10)) ?></time></p>
                <button class="button button--primary" type="submit"><span class="button__label">Save changes</span></button>
            </div>
        </form>
    </article>
</section>
