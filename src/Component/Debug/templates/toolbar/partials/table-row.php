<?php
/**
 * Key-value table row partial.
 *
 * @var string $key        Row label
 * @var string $value      Row value (may contain HTML)
 * @var string $valueClass Optional CSS class for the value cell
 */
$valueClass ??= '';
$classAttr = $valueClass !== '' ? ' class="wpd-kv-val ' . $this->e($valueClass) . '"' : ' class="wpd-kv-val"';
?>
<tr><td class="wpd-kv-key"><?= $this->e($key) ?></td><td<?= $classAttr ?>><?= $this->raw($value) ?></td></tr>
