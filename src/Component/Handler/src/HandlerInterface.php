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
     * Handles an HTTP request through the full lifecycle.
     *
     * Processes the request through the processor chain, serves responses
     * for static files and redirects, or requires the target PHP file
     * (including WordPress) for execution.
     */
    public function handle(Request $request): void;
}
