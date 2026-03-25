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

namespace WpPack\Component\SiteHealth;

final class Result
{
    private function __construct(
        private readonly Status $status,
        private readonly string $label,
        private readonly string $description,
        private readonly string $actions,
    ) {}

    public static function good(string $label, string $description, string $actions = ''): self
    {
        return new self(Status::Good, $label, $description, $actions);
    }

    public static function recommended(string $label, string $description, string $actions = ''): self
    {
        return new self(Status::Recommended, $label, $description, $actions);
    }

    public static function critical(string $label, string $description, string $actions = ''): self
    {
        return new self(Status::Critical, $label, $description, $actions);
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getActions(): string
    {
        return $this->actions;
    }

    /**
     * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
     */
    public function toArray(string $test, string $badgeLabel): array
    {
        return [
            'label' => $this->label,
            'status' => $this->status->value,
            'badge' => [
                'label' => $badgeLabel,
                'color' => $this->status->badgeColor(),
            ],
            'description' => $this->description,
            'actions' => $this->actions,
            'test' => $test,
        ];
    }
}
