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

namespace WpPack\Component\OptionsResolver;

use Symfony\Component\OptionsResolver\OptionsResolver as SymfonyOptionsResolver;

/**
 * WordPress-aware options resolver.
 *
 * Extends Symfony OptionsResolver with auto-cast normalizers.
 * When setAllowedTypes() is called with a single castable type
 * ('int', 'float', or 'bool'), a normalizer is automatically
 * registered to cast string values to that type, and 'string'
 * is also accepted as input to pass type validation.
 */
class OptionsResolver extends SymfonyOptionsResolver
{
    public function setAllowedTypes(string $option, string|array $allowedTypes): static
    {
        if (\is_string($allowedTypes)) {
            $normalizer = $this->createNormalizerForType($allowedTypes);

            if ($normalizer !== null) {
                // Allow string input to pass type validation (WordPress always passes strings)
                parent::setAllowedTypes($option, ['string', $allowedTypes]);
                $this->addNormalizer($option, $normalizer);

                return $this;
            }
        }

        parent::setAllowedTypes($option, $allowedTypes);

        return $this;
    }

    /**
     * @return (\Closure(SymfonyOptionsResolver, mixed): mixed)|null
     */
    private function createNormalizerForType(string $type): ?\Closure
    {
        return match ($type) {
            'int' => static fn(SymfonyOptionsResolver $resolver, mixed $value): int => \is_string($value) ? (int) $value : $value,
            'float' => static fn(SymfonyOptionsResolver $resolver, mixed $value): float => \is_string($value) ? (float) $value : $value,
            'bool' => static function (SymfonyOptionsResolver $resolver, mixed $value): bool {
                if (\is_string($value)) {
                    return \in_array(strtolower($value), ['true', '1', 'yes'], true);
                }

                return (bool) $value;
            },
            default => null,
        };
    }
}
