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
use WPPack\Component\HttpFoundation\RedirectResponse;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;

class TrailingSlashProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->getPathInfo();
        $webRoot = $config->get('web_root');

        if (str_ends_with($path, '/')) {
            return null;
        }

        if ($path === '') {
            return new RedirectResponse('/', 307);
        }

        $fullPath = $webRoot . $path;

        if (is_dir($fullPath)) {
            $url = $path . '/';
            $qs = $request->getQueryString();
            if ($qs !== null) {
                $url .= '?' . $qs;
            }

            return new RedirectResponse($url, 307);
        }

        return null;
    }
}
