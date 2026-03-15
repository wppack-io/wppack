<?php
/**
 * WordPress indicator button.
 *
 * @var string $wpVersion WordPress version string
 * @var string $wpIcon    SVG icon HTML
 */
$content = '<span class="wpd-bar-logo">' . $wpIcon . '</span>';
if ($wpVersion !== '') {
    $content .= '<span class="wpd-bar-version">' . $view->e($wpVersion) . '</span>';
}
?>
<button class="wpd-bar-wp" data-panel="wordpress" title="WordPress"><?= $view->raw($content) ?></button>
