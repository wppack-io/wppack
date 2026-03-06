<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

class BinaryFileResponse extends Response
{
    public readonly string $path;
    public readonly ?string $filename;
    public readonly string $disposition;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $path,
        ?string $filename = null,
        string $disposition = 'attachment',
        int $statusCode = 200,
        array $headers = [],
    ) {
        $this->path = $path;
        $this->filename = $filename;
        $this->disposition = $disposition;

        parent::__construct('', $statusCode, $headers);
    }

    protected function sendContent(): void
    {
        if (!is_file($this->path)) {
            throw new \RuntimeException(sprintf('File "%s" does not exist.', $this->path));
        }

        readfile($this->path);
    }
}
