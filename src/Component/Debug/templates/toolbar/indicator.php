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
    ? ' <span class="wpd-indicator-value" style="color:' . $this->e($colors['fg']) . '">' . $this->e($value) . '</span>'
    : '';

$bgStyle = $colors['bg'] !== 'transparent' ? ' style="background:' . $this->e($colors['bg']) . '"' : '';
$iconStyle = $colorKey !== 'default' ? ' style="color:' . $this->e($colors['fg']) . '"' : '';
$accentAttr = $colorKey !== 'default' ? ' data-accent="' . $this->e($colors['fg']) . '"' : '';
?>
<button class="wpd-indicator" data-panel="<?= $this->e($name) ?>" data-tooltip="<?= $this->e($label) ?>"<?= $bgStyle ?><?= $accentAttr ?>>
    <span class="wpd-indicator-icon"<?= $iconStyle ?>><?= $this->raw($icon) ?></span><?= $this->raw($valueHtml) ?>
</button>
