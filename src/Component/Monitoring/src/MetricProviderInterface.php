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

namespace WpPack\Component\Monitoring;

interface MetricProviderInterface
{
    public function getName(): string;

    public function isAvailable(): bool;

    public function getLabel(): string;

    /**
     * @return list<array{id: string, label: string, type: string, description?: string, elements?: list<array{value: string, label: string}>, getValue?: string}>
     */
    public function getFormFields(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getTemplates(): array;

    /**
     * @return array<string, string>
     */
    public function getDimensionLabels(): array;

    /**
     * @return array<string, string>
     */
    public function getDefaultSettings(): array;

    /**
     * @return array{buttonLabel: string, title: string, content: list<array<string, mixed>>}|null
     */
    public function getSetupGuide(): ?array;

    /**
     * @param array<string, mixed> $settings
     */
    public function validateSettings(array $settings): bool;

    /**
     * @return list<MetricResult>
     */
    public function query(MonitoringProvider $provider, MetricTimeRange $range): array;

    /**
     * @return class-string<ProviderSettings>
     */
    public function getSettingsClass(): string;
}
