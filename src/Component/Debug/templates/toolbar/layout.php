<?php
/**
 * Debug toolbar layout template.
 *
 * @var string $css              Toolbar CSS
 * @var string $js               Toolbar JavaScript
 * @var string $sidebarHtml      Sidebar navigation HTML
 * @var string $contentPanels    Panel content HTML
 * @var string $defaultTitle     Default panel title
 * @var string $indicators       Indicator buttons HTML
 * @var string $wpIndicatorHtml  WordPress indicator HTML
 * @var string $envHtml          Environment indicator HTML
 * @var string $wpMiniIcon       Minimized toolbar icon SVG
 * @var string $closeIcon        Close button icon SVG
 */
?>
<div id="wppack-debug">
<style><?= $this->raw($css) ?></style>
<div class="wpd-overlay" style="display:none">
    <div class="wpd-sidebar">
        <?= $this->raw($sidebarHtml) ?>
    </div>
    <div class="wpd-content">
        <div class="wpd-content-header">
            <span class="wpd-panel-title"><?= $this->e($defaultTitle) ?></span>
            <button class="wpd-panel-close" data-action="close-panel" title="Close"><?= $this->raw($closeIcon) ?></button>
        </div>
        <div class="wpd-content-body">
            <?= $this->raw($contentPanels) ?>
        </div>
    </div>
</div>
<div class="wpd-mini" title="Show WpPack Debug Toolbar">
    <?= $this->raw($wpMiniIcon) ?>
</div>
<div class="wpd-bar">
    <?= $this->raw($wpIndicatorHtml) ?>
    <div class="wpd-bar-indicators-wrap">
        <div class="wpd-bar-indicators">
            <?= $this->raw($indicators) ?>
        </div>
    </div>
    <?= $this->raw($envHtml) ?>
    <button class="wpd-close-btn" data-action="minimize" title="Close toolbar"><?= $this->raw($closeIcon) ?></button>
</div>
<script><?= $this->raw($js) ?></script>
</div>
