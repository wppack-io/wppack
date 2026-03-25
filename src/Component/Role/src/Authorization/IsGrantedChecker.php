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

namespace WpPack\Component\Role\Authorization;

use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\Exception\AccessDeniedException;

final class IsGrantedChecker
{
    public function __construct(
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {}

    /**
     * @param \ReflectionClass<object> $class
     * @return list<IsGranted>
     */
    public static function resolve(\ReflectionClass $class, ?\ReflectionMethod $method = null): array
    {
        $grants = [];

        foreach ($class->getAttributes(IsGranted::class) as $attr) {
            $grants[] = $attr->newInstance();
        }

        if ($method !== null) {
            foreach ($method->getAttributes(IsGranted::class) as $attr) {
                $grants[] = $attr->newInstance();
            }
        }

        return $grants;
    }

    /**
     * @param list<IsGranted> $grants
     */
    public function check(array $grants): void
    {
        foreach ($grants as $grant) {
            if ($this->authorizationChecker !== null) {
                if (!$this->authorizationChecker->isGranted($grant->attribute, $grant->subject)) {
                    throw new AccessDeniedException($grant->message, $grant->statusCode);
                }
            } elseif ($grant->subject !== null ? !current_user_can($grant->attribute, $grant->subject) : !current_user_can($grant->attribute)) {
                throw new AccessDeniedException($grant->message, $grant->statusCode);
            }
        }
    }

    /**
     * @param list<IsGranted> $grants
     */
    public function isAllGranted(array $grants): bool
    {
        foreach ($grants as $grant) {
            if ($this->authorizationChecker !== null) {
                if (!$this->authorizationChecker->isGranted($grant->attribute, $grant->subject)) {
                    return false;
                }
            } elseif ($grant->subject !== null ? !current_user_can($grant->attribute, $grant->subject) : !current_user_can($grant->attribute)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    public static function extractCapability(\ReflectionClass $class): string
    {
        $attributes = $class->getAttributes(IsGranted::class);

        if ($attributes !== []) {
            return $attributes[0]->newInstance()->attribute;
        }

        return 'manage_options';
    }
}
