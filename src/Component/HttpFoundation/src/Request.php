<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

class Request
{
    public readonly ParameterBag $query;
    public readonly ParameterBag $post;
    public readonly ParameterBag $cookies;
    public readonly FileBag $files;
    public readonly ServerBag $server;
    public readonly HeaderBag $headers;

    private ?string $content;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        array $query = [],
        array $post = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
    ) {
        $this->query = new ParameterBag($query);
        $this->post = new ParameterBag($post);
        $this->cookies = new ParameterBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());
        $this->content = $content;
    }

    public static function createFromGlobals(): self
    {
        return new self($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
    }

    /**
     * Gets a parameter from query, then post.
     */
    public function get(string $key, mixed $default = null): mixed
    {
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

    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function getHost(): string
    {
        $host = $this->headers->get('host', '');

        if ($host === null || $host === '') {
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
