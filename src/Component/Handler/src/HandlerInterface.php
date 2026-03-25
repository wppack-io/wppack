<?php

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
