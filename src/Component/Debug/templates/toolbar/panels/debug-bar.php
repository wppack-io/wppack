<?php
/**
 * Debug Bar panel template.
 *
 * @var list<array{title: string, html: string}>                    $panels     Debug Bar panels
 * @var int                                                          $panelCount Panel count
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt        Template formatters
 */
?>
<?php if (empty($panels)): ?>
<div class="wpd-section"><p class="wpd-text-dim">No Debug Bar panels registered.</p></div>
<?php else: ?>
<?php foreach ($panels as $panel): ?>
<div class="wpd-section">
<h4 class="wpd-section-title"><?= $view->e($panel['title']) ?></h4>
<div class="wpd-debug-bar-content"><?= $view->raw($panel['html']) ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
