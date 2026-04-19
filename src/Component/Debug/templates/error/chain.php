<?php
/**
 * Exception chain template.
 *
 * @var list<array>                                                $chain    Exception chain
 * @var \WPPack\Component\Debug\ErrorHandler\ErrorRenderer         $renderer Error renderer for path formatting
 */
if (count($chain) <= 1) {
    return;
}
for ($i = 1, $count = count($chain); $i < $count; $i++):
    $item = $chain[$i];
    ?>
<div class="chain-item">
<div class="chain-item-class"><?= $view->e($item['class']) ?></div>
<div class="chain-item-message"><?= $view->e($item['message']) ?></div>
<?php if (isset($item['trace']) && !empty($item['trace'])): ?>
<div class="chain-item-trace">
<?= $view->include('error/trace', ['trace' => $item['trace'], 'openFirst' => false, 'renderer' => $renderer]) ?>
</div>
<?php endif; ?>
</div>
<?php endfor; ?>
