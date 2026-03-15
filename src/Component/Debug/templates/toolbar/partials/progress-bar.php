<?php
/**
 * Progress bar partial with inline bar + percentage text.
 *
 * @var float  $percentage Percentage value (0-100)
 * @var string $barColor   CSS color for bar fill (e.g. "var(--wpd-green)")
 * @var string $textClass  CSS class for text (e.g. "wpd-text-green")
 * @var string $label      Formatted percentage label (e.g. "82.3%")
 */
?>
<span class="wpd-inline-bar"><span class="wpd-inline-bar-fill" style="width:<?= $this->e(sprintf('%.1f', min($percentage, 100))) ?>%;background:<?= $this->e($barColor) ?>"></span></span><span class="<?= $textClass ?>"><?= $this->e($label) ?></span>
