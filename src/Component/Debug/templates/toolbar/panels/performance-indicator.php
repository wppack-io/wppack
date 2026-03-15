<?php
/**
 * Performance indicator button.
 *
 * @var string                        $value   Formatted time value
 * @var string                        $icon    SVG icon HTML
 * @var array{bg: string, fg: string} $colors  Indicator colors
 * @var string                        $bgStyle Inline background style attribute
 */
?>
<button class="wpd-indicator" data-panel="performance" data-tooltip="Performance"<?= $bgStyle ?>>
    <span class="wpd-indicator-icon" style="color:<?= $this->e($colors['fg']) ?>"><?= $this->raw($icon) ?></span>
    <span class="wpd-indicator-value" style="color:<?= $this->e($colors['fg']) ?>"><?= $this->e($value) ?></span>
</button>
