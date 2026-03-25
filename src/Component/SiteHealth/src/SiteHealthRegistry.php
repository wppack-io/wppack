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

use WpPack\Component\SiteHealth\Attribute\AsDebugInfo;
use WpPack\Component\SiteHealth\Attribute\AsHealthCheck;
use WpPack\Component\SiteHealth\Exception\InvalidArgumentException;
use WpPack\Component\SiteHealth\Exception\LogicException;

final class SiteHealthRegistry
{
    /** @var list<array{check: HealthCheckInterface, attribute: AsHealthCheck}> */
    private array $healthChecks = [];

    /** @var list<array{section: DebugSectionInterface, attribute: AsDebugInfo}> */
    private array $debugSections = [];

    private bool $registered = false;

    public function add(HealthCheckInterface|DebugSectionInterface $object): self
    {
        if ($this->registered) {
            throw new LogicException('Cannot add after register() has been called.');
        }

        $reflection = new \ReflectionClass($object);

        if ($object instanceof HealthCheckInterface) {
            $attributes = $reflection->getAttributes(AsHealthCheck::class);

            if ($attributes === []) {
                throw new InvalidArgumentException(sprintf(
                    'Class "%s" is missing the #[AsHealthCheck] attribute.',
                    $object::class,
                ));
            }

            $this->healthChecks[] = [
                'check' => $object,
                'attribute' => $attributes[0]->newInstance(),
            ];

            return $this;
        }

        $attributes = $reflection->getAttributes(AsDebugInfo::class);

        if ($attributes === []) {
            throw new InvalidArgumentException(sprintf(
                'Class "%s" is missing the #[AsDebugInfo] attribute.',
                $object::class,
            ));
        }

        $this->debugSections[] = [
            'section' => $object,
            'attribute' => $attributes[0]->newInstance(),
        ];

        return $this;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        add_filter('site_status_tests', [$this, 'onSiteStatusTests']);
        add_filter('debug_information', [$this, 'onDebugInformation']);
    }

    /**
     * @param array<string, array<string, mixed>> $tests
     * @return array<string, array<string, mixed>>
     */
    public function onSiteStatusTests(array $tests): array
    {
        foreach ($this->healthChecks as $entry) {
            $attribute = $entry['attribute'];
            $check = $entry['check'];

            if ($attribute->async) {
                $tests['async'][$attribute->id] = [
                    'label' => $attribute->label,
                    'test' => $attribute->id,
                    'async_direct_test' => static fn(): array => $check->run()->toArray($attribute->id, $attribute->category),
                ];
            } else {
                $tests['direct'][$attribute->id] = [
                    'label' => $attribute->label,
                    'test' => static fn(): array => $check->run()->toArray($attribute->id, $attribute->category),
                ];
            }
        }

        return $tests;
    }

    /**
     * @param array<string, array<string, mixed>> $debugInfo
     * @return array<string, array<string, mixed>>
     */
    public function onDebugInformation(array $debugInfo): array
    {
        foreach ($this->debugSections as $entry) {
            $attribute = $entry['attribute'];
            $section = $entry['section'];

            $info = [
                'label' => $attribute->label,
                'fields' => $section->getFields(),
                'private' => $attribute->private,
            ];

            if ($attribute->description !== null) {
                $info['description'] = $attribute->description;
            }

            if ($attribute->showCount) {
                $info['show_count'] = true;
            }

            $debugInfo[$attribute->section] = $info;
        }

        return $debugInfo;
    }
}
