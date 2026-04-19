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

namespace WPPack\Component\Handler\Processor;

use WPPack\Component\Handler\Configuration;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;

interface ProcessorInterface
{
    /**
     * Process the request.
     *
     * @return Request|Response|false|null Request: modified request to pass to the next processor.
     *                                     Response: immediately send this response, stop chain.
     *                                     false: stop chain, send no response (delegate to server).
     *                                     null: continue to the next processor with the current request.
     */
    public function process(Request $request, Configuration $config): Request|Response|false|null;
}
