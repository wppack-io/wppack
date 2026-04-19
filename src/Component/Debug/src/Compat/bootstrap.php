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

// Debug Bar compatibility stubs.
// Loaded via Composer "files" autoload, before plugins.
// Each file is guarded with class_exists() to avoid conflicts with the real plugin.

require __DIR__ . '/DebugBar/Debug_Bar_Panel.php';
require __DIR__ . '/DebugBar/Debug_Bar.php';
