<?php
/**
 * Performance metric card partial.
 *
 * @var string $label Card label
 * @var string $value Display value
 * @var string $unit  Unit suffix (e.g. "ms", "MB")
 * @var string $sub   Sub-label text
 */
?>
<div class="wpd-perf-card">
<div class="wpd-perf-card-value"><?= $this->raw($value) ?><?php if ($unit !== ''): ?><span class="wpd-perf-card-unit"><?= $this->e($unit) ?></span><?php endif; ?></div>
<div class="wpd-perf-card-label"><?= $this->e($label) ?></div>
<?php if ($sub !== ''): ?><div class="wpd-perf-card-sub"><?= $this->raw($sub) ?></div><?php endif; ?>
</div>
