<?php
/**
 * Generic panel template.
 *
 * @var array<string,mixed>                                          $data Collected panel data
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt  Template formatters
 */
if (empty($data)): ?>
<div class="wpd-section"><p class="wpd-text-dim">No data collected.</p></div>
<?php else: ?>
<?= $view->include('toolbar/partials/key-value-section', ['title' => 'Data', 'items' => $data, 'fmt' => $fmt]) ?>
<?php endif; ?>
