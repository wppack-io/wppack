<?php
/**
 * Environment indicator button.
 *
 * @var string $labelParts  HTML label parts for the indicator
 * @var string $tooltipHtml Tooltip HTML content
 */
?>
<div class="wpd-bar-env" data-panel="environment">
<span class="wpd-env-label"><?= $view->raw($labelParts) ?></span>
<div class="wpd-env-tooltip"><?= $view->raw($tooltipHtml) ?></div>
</div>
