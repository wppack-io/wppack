<?php
/**
 * Default indicator button for the debug toolbar.
 *
 * @var string $name       Collector name
 * @var string $label      Collector label
 * @var string $value      Indicator value
 * @var string $colorKey   Color key (green/yellow/red/default)
 * @var array  $colors     Color array with bg and fg keys
 * @var string $icon       SVG icon HTML
 */

$valueHtml = $value !== ''
    ? ' <span class="wpd-indicator-value" style="color:' . $view->e($colors['fg']) . '">' . $view->e($value) . '</span>'
    : '';

$bgStyle = $colors['bg'] !== 'transparent' ? ' style="background:' . $view->e($colors['bg']) . '"' : '';
$iconStyle = $colorKey !== 'default' ? ' style="color:' . $view->e($colors['fg']) . '"' : '';
$accentAttr = $colorKey !== 'default' ? ' data-accent="' . $view->e($colors['fg']) . '"' : '';
?>
<button class="wpd-indicator" data-panel="<?= $view->e($name) ?>" data-tooltip="<?= $view->e($label) ?>"<?= $bgStyle ?><?= $accentAttr ?>>
    <span class="wpd-indicator-icon"<?= $iconStyle ?>><?= $view->raw($icon) ?></span><?= $view->raw($valueHtml) ?>
</button>
