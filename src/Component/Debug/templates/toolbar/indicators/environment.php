<?php
/**
 * Environment indicator button.
 *
 * @var string $labelParts  HTML label parts for the indicator
 * @var string $tooltipHtml Tooltip HTML content
 */
?>
<div class="wpd-bar-env" data-panel="environment">
<span class="wpd-env-label"><?= $this->raw($labelParts) ?></span>
<div class="wpd-env-tooltip"><?= $this->raw($tooltipHtml) ?></div>
</div>
