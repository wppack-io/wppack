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

namespace WPPack\Component\HttpFoundation;

class Request
{
    /**
     * Whether wp_magic_quotes() has been applied to superglobals.
     *
     * wp_magic_quotes() applies addslashes() to $_GET, $_POST, $_COOKIE, and $_SERVER.
     * This is called in wp-settings.php (line 599) AFTER plugins_loaded (line 593)
     * but BEFORE init (line 742).
     *
     * When true, createFromGlobals() applies wp_unslash() to reverse the escaping.
     * Auto-detected via did_action('sanitize_comment_cookies') which fires
     * immediately after wp_magic_quotes() in wp-settings.php (line 606).
     *
     * null = auto-detect, true = forced on, false = forced off
     */
    private static ?bool $magicQuotesApplied = null;

    public readonly ParameterBag $query;
    public readonly ParameterBag $post;
    public readonly ParameterBag $attributes;
    public readonly ParameterBag $cookies;
    public readonly FileBag $files;
    public readonly ServerBag $server;
    public readonly HeaderBag $headers;

    private ?string $content;

    private ?ParameterBag $payload = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        array $query = [],
        array $post = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
    ) {
        $this->query = new ParameterBag($query);
        $this->post = new ParameterBag($post);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new ParameterBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());
        $this->content = $content;
    }

    /**
     * Creates a Request from PHP superglobals.
     *
     * WordPress applies addslashes() to $_GET, $_POST, $_COOKIE, and $_SERVER
     * via wp_magic_quotes() in wp-settings.php. This method reverses that
     * with wp_unslash() so the Request always contains clean, unescaped values.
     *
     * Detection: wp_magic_quotes() is called at wp-settings.php:599,
     * immediately followed by do_action('sanitize_comment_cookies') at line 606.
     * If that action has fired, magic quotes have been applied.
     */
    public static function createFromGlobals(): self
    {
        if (self::isMagicQuotesApplied()) {
            /** @var array<string, mixed> $query */
            $query = wp_unslash($_GET);
            /** @var array<string, mixed> $post */
            $post = wp_unslash($_POST);
            /** @var array<string, mixed> $cookies */
            $cookies = wp_unslash($_COOKIE);
            /** @var array<string, mixed> $server */
            $server = wp_unslash($_SERVER);
        } else {
            /** @var array<string, mixed> $query */
            $query = $_GET;
            /** @var array<string, mixed> $post */
            $post = $_POST;
            /** @var array<string, mixed> $cookies */
            $cookies = $_COOKIE;
            /** @var array<string, mixed> $server */
            $server = $_SERVER;
        }

        /** @var array<string, mixed> $files */
        $files = $_FILES;

        return new self($query, $post, [], $cookies, $files, $server);
    }

    /**
     * Marks that wp_magic_quotes() has been applied to superglobals.
     *
     * Normally auto-detected via did_action('sanitize_comment_cookies').
     * This method exists for explicit control in edge cases where
     * auto-detection is not reliable.
     */
    public static function enableMagicQuotesHandling(): void
    {
        self::$magicQuotesApplied = true;
    }

    /**
     * Disables wp_unslash() in createFromGlobals().
     *
     * Use when createFromGlobals() is called before wp_magic_quotes()
     * has been applied (e.g. during plugins_loaded).
     */
    public static function disableMagicQuotesHandling(): void
    {
        self::$magicQuotesApplied = false;
    }

    /**
     * Resets to auto-detection mode.
     *
     * @internal For testing only.
     */
    public static function resetMagicQuotesHandling(): void
    {
        self::$magicQuotesApplied = null;
    }

    private static function isMagicQuotesApplied(): bool
    {
        // Explicit override takes precedence
        if (self::$magicQuotesApplied !== null) {
            return self::$magicQuotesApplied;
        }

        // Auto-detect: sanitize_comment_cookies fires immediately after
        // wp_magic_quotes() in wp-settings.php
        if (\function_exists('did_action') && did_action('sanitize_comment_cookies') > 0) {
            return true;
        }

        return false;
    }

    /**
     * Gets a parameter from attributes, then query, then post.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->attributes->has($key)) {
            return $this->attributes->get($key);
        }

        if ($this->query->has($key)) {
            return $this->query->get($key);
        }

        if ($this->post->has($key)) {
            return $this->post->get($key);
        }

        return $default;
    }

    public function getMethod(): string
    {
        return strtoupper($this->server->getString('REQUEST_METHOD', 'GET'));
    }

    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    public function getUri(): string
    {
        return $this->server->getString('REQUEST_URI', '/');
    }

    public function getPathInfo(): string
    {
        $uri = $this->getUri();
        $queryPos = strpos($uri, '?');

        if ($queryPos !== false) {
            return substr($uri, 0, $queryPos);
        }

        return $uri;
    }

    public function getQueryString(): ?string
    {
        $qs = $this->server->getString('QUERY_STRING');

        if ($qs !== '') {
            return $qs;
        }

        $uri = $this->server->getString('REQUEST_URI');
        $queryPos = strpos($uri, '?');

        if ($queryPos !== false) {
            $qs = substr($uri, $queryPos + 1);

            return $qs !== '' ? $qs : null;
        }

        return null;
    }

    /**
     * Creates a Request from a URI string.
     *
     * Useful for testing or creating requests programmatically
     * outside of an HTTP context.
     *
     * @param array<string, mixed> $parameters GET or POST parameters depending on method
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public static function create(
        string $uri,
        string $method = 'GET',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
    ): self {
        $defaults = [
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
        ];

        $components = parse_url($uri);

        if (isset($components['host'])) {
            $defaults['SERVER_NAME'] = $components['host'];
            $defaults['HTTP_HOST'] = $components['host'];
        }

        if (isset($components['port'])) {
            $defaults['SERVER_PORT'] = $components['port'];
            $defaults['HTTP_HOST'] .= ':' . $components['port'];
        }

        if (isset($components['scheme']) && $components['scheme'] === 'https') {
            $defaults['HTTPS'] = 'on';
            $defaults['SERVER_PORT'] = $components['port'] ?? 443;
        }

        if (isset($components['query'])) {
            $defaults['QUERY_STRING'] = $components['query'];
            parse_str($components['query'], $queryParams);
        } else {
            $queryParams = [];
        }

        $server = array_merge($defaults, $server);

        if (\in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $merged = $queryParams;
            $post = $parameters;
        } else {
            $merged = array_merge($queryParams, $parameters);
            $post = [];
        }

        $query = [];
        foreach ($merged as $key => $value) {
            $query[(string) $key] = $value;
        }

        return new self($query, $post, [], $cookies, $files, $server, $content);
    }

    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function getHost(): string
    {
        $host = $this->headers->get('host', '');

        if ($host === '') {
            $host = $this->server->getString('SERVER_NAME');
        }

        // Remove port from host
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);

        return $host;
    }

    public function getPort(): int
    {
        return $this->server->getInt('SERVER_PORT', $this->isSecure() ? 443 : 80);
    }

    public function getClientIp(): ?string
    {
        $forwarded = $this->server->getString('HTTP_X_FORWARDED_FOR');

        if ($forwarded !== '') {
            $ips = array_map('trim', explode(',', $forwarded));

            return $ips[0];
        }

        $remoteAddr = $this->server->getString('REMOTE_ADDR');

        return $remoteAddr !== '' ? $remoteAddr : null;
    }

    public function getContentType(): ?string
    {
        return $this->headers->get('content-type');
    }

    public function getContent(): string
    {
        if ($this->content === null) {
            $this->content = (string) file_get_contents('php://input');
        }

        return $this->content;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \JsonException
     */
    public function toArray(): array
    {
        $content = $this->getContent();

        if ($content === '') {
            return [];
        }

        /** @var array<string, mixed> */
        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }

    public function getPayload(): ParameterBag
    {
        if ($this->payload === null) {
            $this->payload = $this->post->count() > 0
                ? new ParameterBag($this->post->all())
                : new ParameterBag($this->toArray());
        }

        return $this->payload;
    }

    public function isAjax(): bool
    {
        return $this->headers->get('x-requested-with') === 'XMLHttpRequest';
    }

    public function isSecure(): bool
    {
        $https = $this->server->getString('HTTPS');

        if ($https !== '' && $https !== 'off') {
            return true;
        }

        return $this->server->getInt('SERVER_PORT') === 443;
    }

    public function isJson(): bool
    {
        $contentType = $this->getContentType();

        return $contentType !== null && str_contains($contentType, 'json');
    }
}
