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

class PhpFileProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->server->get('PHP_SELF');
        $webRoot = $config->get('web_root');
        $fullPath = $webRoot . $path;

        if (is_file($fullPath) && str_ends_with($path, '.php')) {
            $request->server->set('SCRIPT_NAME', $path);
            $request->server->set('SCRIPT_FILENAME', $fullPath);

            return $request;
        }

        return null;
    }
}
