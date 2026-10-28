<?php
/**
 * A labelled input with icon, inline error slot and (for passwords) a visibility toggle.
 *
 * @var string      $name
 * @var string      $label
 * @var string|null $type         default "text"
 * @var string|null $id           default = $name
 * @var string|null $icon         icon name from App\View\Icons
 * @var string|null $value
 * @var string|null $placeholder
 * @var string|null $autocomplete
 * @var string|null $hint
 * @var string|null $aside        trusted HTML shown next to the label (e.g. a link)
 * @var bool|null   $required
 * @var bool|null   $autofocus
 * @var bool|null   $readonly
 * @var bool|null   $strength     show the live password-strength meter
 * @var int|null    $maxlength
 */
$id ??= $name;
$type ??= 'text';
$isPassword = $type === 'password';
$describedBy = trim((isset($hint) ? "{$id}-hint " : '') . "{$id}-error");
?>
<div class="field" data-field="<?= e($name) ?>">
    <div class="field__label-row">
        <label class="field__label" for="<?= e($id) ?>"><?= e($label) ?></label>
        <?= $aside ?? '' ?>
    </div>
    <div class="field__control">
        <?php if (isset($icon)): ?><?= icon($icon, 'field__icon') ?><?php endif; ?>
        <input
            class="input<?= isset($icon) ? '' : ' input--plain' ?>"
            id="<?= e($id) ?>"
            name="<?= e($name) ?>"
            type="<?= e($type) ?>"
            <?php if (isset($value)): ?>value="<?= e($value) ?>"<?php endif; ?>
            <?php if (isset($placeholder)): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
            <?php if (isset($autocomplete)): ?>autocomplete="<?= e($autocomplete) ?>"<?php endif; ?>
            <?php if (isset($maxlength)): ?>maxlength="<?= (int) $maxlength ?>"<?php endif; ?>
            <?php if ($type === 'email'): ?>inputmode="email" autocapitalize="none" spellcheck="false"<?php endif; ?>
            <?php if ($required ?? false): ?>required<?php endif; ?>
            <?php if ($autofocus ?? false): ?>autofocus<?php endif; ?>
            <?php if ($readonly ?? false): ?>readonly<?php endif; ?>
            aria-describedby="<?= e($describedBy) ?>"
        >
        <?php if ($isPassword): ?>
            <button class="field__toggle" type="button" data-toggle-password aria-controls="<?= e($id) ?>" aria-label="Show password" aria-pressed="false">
                <?= icon('eye', 'when-hidden') ?>
                <?= icon('eye-off', 'when-visible') ?>
            </button>
        <?php endif; ?>
    </div>
    <?php if ($strength ?? false): ?>
        <div class="strength" data-strength-for="<?= e($id) ?>" data-score="0">
            <div class="strength__bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <span class="strength__label" aria-live="polite">Use 8+ characters with letters and numbers</span>
        </div>
    <?php endif; ?>
    <?php if ($isPassword): ?>
        <p class="field__caps" hidden><?= icon('triangle-alert') ?> Caps Lock is on</p>
    <?php endif; ?>
    <?php if (isset($hint)): ?>
        <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
    <?php endif; ?>
    <p class="field__error" id="<?= e($id) ?>-error" hidden></p>
</div>
