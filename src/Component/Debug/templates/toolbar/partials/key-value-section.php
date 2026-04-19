<?php
/**
 * Key-value section partial.
 *
 * @var string                                                       $title Section heading
 * @var array<string,mixed>                                          $items Key-value pairs to display
 * @var \WPPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt   Template formatters
 */
?>
<div class="wpd-section">
<h4 class="wpd-section-title"><?= $view->e($title) ?></h4>
<table class="wpd-table wpd-table-kv">
<?php foreach ($items as $key => $val): ?>
<?= $view->include('toolbar/partials/table-row', ['key' => $key, 'value' => $fmt->value($val)]) ?>
<?php endforeach; ?>
</table>
</div>
