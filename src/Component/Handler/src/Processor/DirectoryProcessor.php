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

class DirectoryProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->server->get('PHP_SELF') ?: $request->getPathInfo();
        $webRoot = $config->get('web_root');
        $fullPath = $webRoot . $path;

        if (!is_dir($fullPath)) {
            return null;
        }

        $indexFiles = $config->get('index_files', ['index.php', 'index.html', 'index.htm']);

        foreach ($indexFiles as $index) {
            $indexFilePath = rtrim($fullPath, '/') . '/' . $index;

            if (is_file($indexFilePath)) {
                $indexUrl = rtrim($path, '/') . '/' . $index;
                $request->server->set('PHP_SELF', $indexUrl);
                $request->attributes->set('directory_index', true);

                return null;
            }
        }

        if ($config->get('security.allow_directory_listing', false)) {
            return $this->generateDirectoryListing($fullPath, $path);
        }

        return null;
    }

    private function generateDirectoryListing(string $dir, string $path): Response
    {
        $files = scandir($dir);
        if ($files === false) {
            return new Response('Directory listing failed', 500);
        }

        $html = \sprintf('<h1>Index of %s</h1><ul>', htmlspecialchars($path));

        foreach ($files as $file) {
            if ($file === '.') {
                continue;
            }

            $displayName = htmlspecialchars($file);
            $href = htmlspecialchars($file);

            if ($file === '..') {
                $parentPath = \dirname($path);
                $href = $parentPath === '/' ? '/' : $parentPath . '/';
            }

            $html .= \sprintf('<li><a href="%s">%s</a></li>', $href, $displayName);
        }

        $html .= '</ul>';

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
