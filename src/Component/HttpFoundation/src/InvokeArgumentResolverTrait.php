<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

use WpPack\Component\Security\Attribute\CurrentUser;

/**
 * Resolves method parameter injection for admin-side page/widget registries.
 *
 * Expects the consuming class to have the following properties:
 *
 * @property-read ?Request $request
 * @property-read ?(\WpPack\Component\Security\Security) $security
 */
trait InvokeArgumentResolverTrait
{
    private function createArgumentResolver(object $target, string $methodName = '__invoke'): ?\Closure
    {
        if (!method_exists($target, $methodName)) {
            return null;
        }

        $method = new \ReflectionMethod($target, $methodName);
        $params = $method->getParameters();

        if ($params === []) {
            return null;
        }

        /** @var array<int, 'request'|'currentUser'> */
        $injections = [];

        foreach ($params as $index => $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                $injections[$index] = 'request';
                continue;
            }
            if ($parameter->getAttributes(CurrentUser::class) !== []) {
                $injections[$index] = 'currentUser';
                continue;
            }
        }

        if ($injections === []) {
            return null;
        }

        $request = $this->request;
        $security = $this->security;

        return static function () use ($injections, $request, $security): array {
            $args = [];
            foreach ($injections as $type) {
                $args[] = match ($type) {
                    'request' => $request,
                    'currentUser' => $security?->getUser(),
                };
            }
            return $args;
        };
    }
}
