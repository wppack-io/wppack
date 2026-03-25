<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Processor;

use WpPack\Component\Handler\Configuration;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

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
