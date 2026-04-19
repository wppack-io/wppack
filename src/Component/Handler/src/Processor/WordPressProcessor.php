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

class WordPressProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        if ($request->server->has('SCRIPT_FILENAME')) {
            $scriptFilename = $request->server->get('SCRIPT_FILENAME');
            if (is_file($scriptFilename)) {
                return null;
            }
        }

        $webRoot = $config->get('web_root');
        $wpIndex = $config->get('wordpress_index', '/index.php');
        $indexPath = $webRoot . $wpIndex;

        if (!is_file($indexPath)) {
            throw new \RuntimeException(\sprintf(
                'WordPress index.php not found at: %s',
                $indexPath,
            ));
        }

        $request->server->set('PHP_SELF', $wpIndex);
        $request->server->set('SCRIPT_NAME', $wpIndex);
        $request->server->set('SCRIPT_FILENAME', $indexPath);

        return $request;
    }
}
