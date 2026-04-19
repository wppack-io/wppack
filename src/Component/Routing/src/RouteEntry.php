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

namespace WPPack\Component\Routing;

use WPPack\Component\HttpFoundation\BinaryFileResponse;
use WPPack\Component\HttpFoundation\Exception\HttpException;
use WPPack\Component\HttpFoundation\Exception\NotFoundException;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\RedirectResponse;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Mime\MimeTypes;
use WPPack\Component\Routing\Response\BlockTemplateResponse;
use WPPack\Component\Routing\Response\TemplateResponse;

/** @internal */
final class RouteEntry
{
    public readonly string $query;
    /** @var list<string> */
    public readonly array $queryVars;

    private ?TemplateResponse $pendingTemplate = null;
    private ?string $pendingBlockTemplate = null;

    public readonly bool $trailingSlash;

    /**
     * @param list<array{string, string}> $rewriteTags
     * @param list<string> $methods
     */
    public function __construct(
        public readonly string $name,
        public readonly string $regex,
        string $query,
        public readonly RoutePosition $position,
        public readonly array $rewriteTags,
        private readonly \Closure $handler,
        public readonly string $path = '',
        public readonly array $methods = [],
        private readonly ?Request $request = null,
    ) {
        $this->trailingSlash = $path !== '' && str_ends_with($path, '/');

        $parsed = self::parseQueryVars($query);
        if ($parsed === [] && $name !== '') {
            $sentinel = '_route_' . str_replace(['-', '.'], '_', $name);
            $separator = str_ends_with($query, '?') ? '' : '&';
            $this->query = $query . $separator . $sentinel . '=1';
            $this->queryVars = [$sentinel];
        } else {
            $this->query = $query;
            $this->queryVars = $parsed;
        }
    }

    public function registerRoute(): void
    {
        foreach ($this->rewriteTags as [$tag, $regex]) {
            add_rewrite_tag($tag, $regex);
        }
        add_rewrite_rule($this->regex, $this->query, $this->position->value);
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function filterQueryVars(array $vars): array
    {
        return array_values(array_unique(array_merge($vars, $this->queryVars)));
    }

    public function handleTemplateRedirect(): void
    {
        if ($this->methods !== [] && $this->request !== null
            && !\in_array($this->request->getMethod(), $this->methods, true)
        ) {
            return;
        }

        foreach ($this->queryVars as $var) {
            $value = get_query_var($var);
            if ($value !== '' && $value !== false) {
                $this->redirectTrailingSlash();
                $this->dispatch();

                return;
            }
        }
    }

    /**
     * @param string|false $redirectUrl
     * @return string|false
     */
    public function filterRedirectCanonical(string|false $redirectUrl): string|false
    {
        if ($redirectUrl === false) {
            return false;
        }

        foreach ($this->queryVars as $var) {
            $value = get_query_var($var);
            if ($value !== '' && $value !== false) {
                return $this->normalizeTrailingSlash($redirectUrl);
            }
        }

        return $redirectUrl;
    }

    private function normalizeTrailingSlash(string $url): string
    {
        $queryPos = strpos($url, '?');
        $base = $queryPos !== false ? substr($url, 0, $queryPos) : $url;
        $suffix = $queryPos !== false ? substr($url, $queryPos) : '';

        $normalized = $this->trailingSlash
            ? rtrim($base, '/') . '/'
            : rtrim($base, '/');

        return $normalized . $suffix;
    }

    public function filterTemplateInclude(string $template): string
    {
        if ($this->pendingTemplate !== null) {
            return $this->pendingTemplate->template;
        }

        if ($this->pendingBlockTemplate !== null) {
            return $this->pendingBlockTemplate;
        }

        return $template;
    }

    /**
     * Compiles a path pattern into a WordPress rewrite regex.
     *
     * @param array<string, string> $requirements
     */
    public static function compilePath(string $path, array $requirements = []): string
    {
        $path = trim($path, '/');
        $params = self::extractParams($path);

        $regex = $path;
        foreach ($params as $param) {
            $pattern = $requirements[$param] ?? '[^/]+';
            $regex = str_replace('{' . $param . '}', '(?P<' . $param . '>' . $pattern . ')', $regex);
        }

        return '^' . $regex . '/?$';
    }

    /**
     * Builds a WordPress query string from a path pattern and optional static vars.
     *
     * @param array<string, string> $vars
     */
    public static function buildQueryFromPath(string $path, array $vars = []): string
    {
        $params = self::extractParams($path);
        $parts = [];

        foreach ($vars as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        foreach ($params as $index => $param) {
            $parts[] = $param . '=$matches[' . ($index + 1) . ']';
        }

        return 'index.php?' . implode('&', $parts);
    }

    /**
     * Extracts parameter names from a path pattern.
     *
     * @return list<string>
     */
    public static function extractParams(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return $matches[1];
    }

    /**
     * @codeCoverageIgnore
     */
    private function redirectTrailingSlash(): void
    {
        if ($this->request === null) {
            return;
        }

        $method = $this->request->getMethod();
        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }

        $path = $this->request->getPathInfo();
        $hasTrailingSlash = $path !== '/' && str_ends_with($path, '/');

        if ($this->trailingSlash === $hasTrailingSlash) {
            return;
        }

        $canonicalPath = $this->trailingSlash ? $path . '/' : rtrim($path, '/');
        $queryString = $this->request->getQueryString();
        $url = $canonicalPath . ($queryString !== null ? '?' . $queryString : '');

        wp_safe_redirect($url, 301);
        exit;
    }

    private function dispatch(): void
    {
        try {
            $response = ($this->handler)();

            if ($response === null) {
                return;
            }

            $this->sendResponse($response);
        } catch (HttpException $e) {
            $this->handleException($e);
        }
    }

    private function sendResponse(Response $response): void
    {
        match (true) {
            $response instanceof TemplateResponse => $this->handleTemplate($response),
            $response instanceof BlockTemplateResponse => $this->handleBlockTemplate($response),
            $response instanceof JsonResponse => $this->handleJson($response), // @codeCoverageIgnore
            $response instanceof RedirectResponse => $this->handleRedirect($response), // @codeCoverageIgnore
            $response instanceof BinaryFileResponse => $this->handleFile($response), // @codeCoverageIgnore
            default => $this->handleHtml($response), // @codeCoverageIgnore
        };
    }

    private function handleException(HttpException $e): void
    {
        if ($e instanceof NotFoundException) {
            global $wp_query;
            $wp_query->set_404();
        }

        do_action('wppack_routing_exception', $e);

        $response = apply_filters('wppack_routing_exception_response', null, $e);
        if ($response instanceof Response) {
            $this->sendResponse($response);

            return;
        }

        nocache_headers();

        // @codeCoverageIgnoreStart
        $blockTemplate = get_block_template(
            get_stylesheet() . '//' . $e->getStatusCode(),
        );
        if ($blockTemplate !== null) {
            $this->handleBlockTemplate(new BlockTemplateResponse(
                (string) $e->getStatusCode(),
                ['exception' => $e],
                $e->getStatusCode(),
            ));

            return;
        }

        $template = locate_template([sprintf('%d.php', $e->getStatusCode())]);
        if ($template !== '') {
            $this->handleTemplate(new TemplateResponse(
                $template,
                ['exception' => $e],
                $e->getStatusCode(),
            ));

            return;
        }
        // @codeCoverageIgnoreEnd

        wp_die(
            $e->getMessage(),
            $e->getErrorCode(),
            ['response' => $e->getStatusCode()],
        );
    }

    /**
     * @codeCoverageIgnore Cannot test header sending in PHPUnit (headers_sent() always returns true)
     */
    private function sendHeaders(Response $response): void
    {
        if (headers_sent()) {
            return;
        }

        status_header($response->statusCode);
        foreach ($response->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }
    }

    private function handleTemplate(TemplateResponse $response): void
    {
        $this->sendHeaders($response);
        foreach ($response->context as $key => $value) {
            set_query_var($key, $value);
        }
        $this->pendingTemplate = $response;
    }

    /**
     * @codeCoverageIgnore Requires block theme with matching template; untestable in PHPUnit
     */
    private function handleBlockTemplate(BlockTemplateResponse $response): void
    {
        $this->sendHeaders($response);
        foreach ($response->context as $key => $value) {
            set_query_var($key, $value);
        }

        $blockTemplate = get_block_template(get_stylesheet() . '//' . $response->slug);
        if ($blockTemplate !== null) {
            global $_wp_current_template_content;
            $_wp_current_template_content = $blockTemplate->content;
            $this->pendingBlockTemplate = ABSPATH . WPINC . '/template-canvas.php'; // @phpstan-ignore constant.notFound
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleJson(JsonResponse $response): void
    {
        $this->sendHeaders($response);
        wp_send_json($response->data, $response->statusCode);
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleRedirect(RedirectResponse $response): void
    {
        $this->sendHeaders($response);
        if ($response->safe) {
            $validated = wp_validate_redirect($response->url, '');
            if ($validated === '') {
                throw new HttpException(
                    \sprintf('Redirect to an untrusted external URL was blocked: %s', $response->url),
                    403,
                );
            }
            wp_safe_redirect($response->url, $response->statusCode);
        } else {
            wp_redirect($response->url, $response->statusCode);
        }
        exit;
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleFile(BinaryFileResponse $response): void
    {
        $filename = $response->filename ?? basename($response->path);
        // Static singleton is synced with DI-managed instance via MimeServiceProvider::setDefault()
        $mimeType = MimeTypes::getDefault()->guessMimeType($response->path) ?? 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        $safeFilename = str_replace(['"', "\r", "\n"], '', $filename);
        header('Content-Disposition: ' . $response->disposition . '; filename="' . $safeFilename . '"');
        header('Content-Length: ' . (string) filesize($response->path));
        $this->sendHeaders($response);

        readfile($response->path);
        exit;
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleHtml(Response $response): void
    {
        $this->sendHeaders($response);
        echo $response->content;
        exit;
    }

    /**
     * @return list<string>
     */
    public static function parseQueryVars(string $query): array
    {
        $queryString = preg_replace('/^index\.php\?/', '', $query);
        preg_match_all('/(?:^|&)([^=&]+)=\$matches\[/', (string) $queryString, $matches);

        return $matches[1];
    }
}
