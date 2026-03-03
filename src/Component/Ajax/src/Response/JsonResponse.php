<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Response;

final class JsonResponse
{
    public function __construct(
        public readonly mixed $data,
        public readonly bool $success,
        public readonly int $statusCode = 200,
    ) {}

    public static function success(mixed $data = null, int $statusCode = 200): self
    {
        return new self($data, true, $statusCode);
    }

    public static function error(mixed $data = null, int $statusCode = 400): self
    {
        return new self($data, false, $statusCode);
    }

    /**
     * @codeCoverageIgnore
     */
    public function send(): never
    {
        if ($this->success) {
            wp_send_json_success($this->data, $this->statusCode);
        } else {
            wp_send_json_error($this->data, $this->statusCode);
        }

        exit; // unreachable: wp_send_json_* calls die()
    }
}
