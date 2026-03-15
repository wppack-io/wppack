<?php
/**
 * Key-value table row partial.
 *
 * @var string $key        Row label
 * @var string $value      Row value (may contain HTML)
 * @var string $valueClass Optional CSS class for the value cell
 */
$valueClass ??= '';
$classAttr = $valueClass !== '' ? ' class="wpd-kv-val ' . $view->e($valueClass) . '"' : ' class="wpd-kv-val"';
?>
<tr><td class="wpd-kv-key"><?= $view->e($key) ?></td><td<?= $classAttr ?>><?= $view->raw($value) ?></td></tr>
