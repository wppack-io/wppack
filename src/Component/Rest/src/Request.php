<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

final class Request
{
    public function __construct(
        private readonly \WP_REST_Request $wpRequest,
    ) {}

    public function getParam(string $name): mixed
    {
        return $this->wpRequest->get_param($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->wpRequest->get_params();
    }

    public function getHeader(string $name): ?string
    {
        return $this->wpRequest->get_header($name);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getHeaders(): array
    {
        return $this->wpRequest->get_headers();
    }

    public function getMethod(): string
    {
        return $this->wpRequest->get_method();
    }

    public function getBody(): string
    {
        return $this->wpRequest->get_body();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonParams(): array
    {
        return $this->wpRequest->get_json_params();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFileParams(): array
    {
        return $this->wpRequest->get_file_params();
    }

    /**
     * @return array<string, mixed>
     */
    public function getUrlParams(): array
    {
        return $this->wpRequest->get_url_params();
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->wpRequest->get_query_params();
    }

    /**
     * @return array<string, mixed>
     */
    public function getBodyParams(): array
    {
        return $this->wpRequest->get_body_params();
    }

    public function getWpRequest(): \WP_REST_Request
    {
        return $this->wpRequest;
    }
}
