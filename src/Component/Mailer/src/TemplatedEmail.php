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

namespace WpPack\Component\Mailer;

class TemplatedEmail extends Email
{
    private ?string $htmlTemplate = null;
    private ?string $textTemplate = null;
    /** @var array<string, mixed> */
    private array $context = [];

    public function htmlTemplate(?string $template): static
    {
        $this->htmlTemplate = $template;

        return $this;
    }

    public function textTemplate(?string $template): static
    {
        $this->textTemplate = $template;

        return $this;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function context(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getHtmlTemplate(): ?string
    {
        return $this->htmlTemplate;
    }

    public function getTextTemplate(): ?string
    {
        return $this->textTemplate;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }
}
