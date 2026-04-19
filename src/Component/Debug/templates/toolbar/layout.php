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
<style><?= $view->raw($css) ?></style>
<div class="wpd-overlay" style="display:none">
    <div class="wpd-sidebar">
        <?= $view->raw($sidebarHtml) ?>
    </div>
    <div class="wpd-content">
        <div class="wpd-content-header">
            <span class="wpd-panel-title"><?= $view->e($defaultTitle) ?></span>
            <button class="wpd-panel-close" data-action="close-panel" title="Close"><?= $view->raw($closeIcon) ?></button>
        </div>
        <div class="wpd-content-body">
            <?= $view->raw($contentPanels) ?>
        </div>
    </div>
</div>
<div class="wpd-mini" title="Show WPPack Debug Toolbar">
    <?= $view->raw($wpMiniIcon) ?>
</div>
<div class="wpd-bar">
    <?= $view->raw($wpIndicatorHtml) ?>
    <div class="wpd-bar-indicators-wrap">
        <div class="wpd-bar-indicators">
            <?= $view->raw($indicators) ?>
        </div>
    </div>
    <?= $view->raw($envHtml) ?>
    <button class="wpd-close-btn" data-action="minimize" title="Close toolbar"><?= $view->raw($closeIcon) ?></button>
</div>
<script><?= $view->raw($js) ?></script>
</div>
