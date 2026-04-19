<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);
/** @var mixed $value */
if (is_bool($value)) {
    echo $value
        ? '<span class="wpd-text-green">true</span>'
        : '<span class="wpd-text-red">false</span>';
} elseif ($value === null) {
    echo '<span class="wpd-text-dim">null</span>';
} elseif (is_array($value)) {
    echo '<code>' . $view->e(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]') . '</code>';
} else {
    echo $view->e((string) $value);
}
