<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Processor;

use WpPack\Component\Handler\Configuration;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

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
