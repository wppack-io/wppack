<?php
/** @var mixed $value */
if (is_bool($value)) {
    echo $value
        ? '<span class="wpd-text-green">true</span>'
        : '<span class="wpd-text-red">false</span>';
} elseif ($value === null) {
    echo '<span class="wpd-text-dim">null</span>';
} elseif (is_array($value)) {
    echo '<code>' . $this->e(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]') . '</code>';
} else {
    echo $this->e((string) $value);
}
