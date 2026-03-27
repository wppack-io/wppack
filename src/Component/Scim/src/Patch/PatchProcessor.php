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

namespace WpPack\Component\Scim\Patch;

use WpPack\Component\Scim\Exception\InvalidPatchException;
use WpPack\Component\Scim\Exception\MutabilityException;

final class PatchProcessor
{
    private const IMMUTABLE_ATTRIBUTES = ['userName', 'id'];

    /**
     * Apply a list of patch operations to a SCIM resource representation.
     *
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    public function apply(array $resource, PatchRequest $patchRequest): array
    {
        foreach ($patchRequest->operations as $operation) {
            $resource = $this->applyOperation($resource, $operation);
        }

        return $resource;
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    private function applyOperation(array $resource, PatchOperation $operation): array
    {
        return match ($operation->op) {
            'add' => $this->applyAdd($resource, $operation),
            'replace' => $this->applyReplace($resource, $operation),
            'remove' => $this->applyRemove($resource, $operation),
            default => throw new InvalidPatchException(sprintf('Unsupported operation: "%s".', $operation->op)),
        };
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    private function applyAdd(array $resource, PatchOperation $operation): array
    {
        if ($operation->path === null) {
            // No path: merge value into resource
            if (!\is_array($operation->value)) {
                throw new InvalidPatchException('Add without path requires an object value.');
            }

            $this->checkImmutableAttributes($operation->value, $resource);

            return array_merge($resource, $operation->value);
        }

        $this->checkImmutablePath($operation->path);
        $segments = PathParser::parse($operation->path);

        return PathParser::setValueAtPath($resource, $segments, $operation->value);
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    private function applyReplace(array $resource, PatchOperation $operation): array
    {
        if ($operation->path === null) {
            // No path: merge value into resource
            if (!\is_array($operation->value)) {
                throw new InvalidPatchException('Replace without path requires an object value.');
            }

            $this->checkImmutableAttributes($operation->value, $resource);

            return array_merge($resource, $operation->value);
        }

        $this->checkImmutablePath($operation->path);
        $segments = PathParser::parse($operation->path);

        return PathParser::setValueAtPath($resource, $segments, $operation->value);
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    private function applyRemove(array $resource, PatchOperation $operation): array
    {
        if ($operation->path === null) {
            throw new InvalidPatchException('Remove operation requires a path.', 'noTarget');
        }

        $this->checkImmutablePath($operation->path);
        $segments = PathParser::parse($operation->path);

        return PathParser::removeValueAtPath($resource, $segments);
    }

    private function checkImmutablePath(string $path): void
    {
        $rootAttribute = explode('.', $path)[0];
        if (\in_array($rootAttribute, self::IMMUTABLE_ATTRIBUTES, true)) {
            throw new MutabilityException(sprintf('Attribute "%s" is immutable.', $rootAttribute));
        }
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $resource
     */
    private function checkImmutableAttributes(array $values, array $resource): void
    {
        foreach (self::IMMUTABLE_ATTRIBUTES as $attr) {
            if (isset($values[$attr]) && isset($resource[$attr]) && $values[$attr] !== $resource[$attr]) {
                throw new MutabilityException(sprintf('Attribute "%s" is immutable.', $attr));
            }
        }
    }
}
