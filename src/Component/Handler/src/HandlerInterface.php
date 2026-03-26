<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Handler;

use WpPack\Component\HttpFoundation\Request;

interface HandlerInterface
{
    /**
     * Runs the front controller.
     *
     * Processes the request through the processor chain, serves responses
     * for static files and redirects, or returns the resolved PHP file path.
     * The caller is responsible for requiring the returned file — typically
     * in global scope so that WordPress admin globals are set correctly.
     *
     * Returns the resolved file path, or null if the response was already
     * sent (static files, redirects, errors).
     */
    public function run(?Request $request = null): ?string;
}
