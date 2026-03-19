<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Authorization;

use WpPack\Component\Security\Attribute\IsGranted;
use WpPack\Component\Security\Exception\AccessDeniedException;
use WpPack\Component\Security\Security;

final class IsGrantedChecker
{
    public function __construct(
        private readonly ?Security $security = null,
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
            if ($this->security !== null) {
                if (!$this->security->isGranted($grant->attribute, $grant->subject)) {
                    throw new AccessDeniedException($grant->message);
                }
            } elseif ($grant->subject !== null ? !current_user_can($grant->attribute, $grant->subject) : !current_user_can($grant->attribute)) {
                throw new AccessDeniedException($grant->message);
            }
        }
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
