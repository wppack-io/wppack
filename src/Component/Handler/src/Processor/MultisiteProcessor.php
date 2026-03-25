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

namespace WpPack\Component\Handler\Processor;

use WpPack\Component\Handler\Configuration;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

class MultisiteProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        if (!$config->get('multisite.enabled', false)) {
            return null;
        }

        $path = $request->server->get('PHP_SELF') ?: $request->getPathInfo();
        $pattern = $config->get('multisite.pattern');
        $replacement = $config->get('multisite.replacement');

        if (!$pattern || !$replacement) {
            return null;
        }

        $rewrittenPath = preg_replace($pattern, $replacement, $path);

        if ($rewrittenPath === null || $rewrittenPath === $path) {
            return null;
        }

        $request->server->set('PHP_SELF', $rewrittenPath);
        $request->attributes->set('original_path', $path);

        return null;
    }
}
