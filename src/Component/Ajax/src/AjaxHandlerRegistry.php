<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Ajax;

use WPPack\Component\Ajax\Attribute\Ajax;
use WPPack\Component\HttpFoundation\Exception\ForbiddenException;
use WPPack\Component\HttpFoundation\Exception\HttpException;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Role\Exception\AccessDeniedException;
use WPPack\Component\Security\Attribute\CurrentUser;
use WPPack\Component\Security\Security;

final class AjaxHandlerRegistry
{
    public function __construct(
        private readonly ?Request $request = null,
        private readonly ?Security $security = null,
        private readonly ?IsGrantedChecker $isGrantedChecker = null,
    ) {}

    public function register(object $subscriber): void
    {
        if ($this->security !== null && $subscriber instanceof AbstractAjaxController) {
            $subscriber->setSecurity($this->security);
        }

        $reflection = new \ReflectionClass($subscriber);
        $checker = $this->isGrantedChecker ?? new IsGrantedChecker($this->security);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Ajax::class);

            foreach ($attributes as $attribute) {
                $handler = $attribute->newInstance();
                $grants = IsGrantedChecker::resolve($reflection, $method);
                $callback = $this->createCallback($subscriber, $method, $handler, $grants, $checker);

                if ($handler->access === Access::Public || $handler->access === Access::Authenticated) {
                    add_action("wp_ajax_{$handler->action}", $callback, $handler->priority);
                }

                if ($handler->access === Access::Public || $handler->access === Access::Guest) {
                    add_action("wp_ajax_nopriv_{$handler->action}", $callback, $handler->priority);
                }
            }
        }
    }

    /**
     * @param list<IsGranted> $grants
     */
    private function createCallback(object $subscriber, \ReflectionMethod $method, Ajax $handler, array $grants, IsGrantedChecker $checker): \Closure
    {
        $methodName = $method->getName();
        $requestParamIndex = null;
        /** @var list<array{index: int}> */
        $currentUserParams = [];

        foreach ($method->getParameters() as $index => $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                $requestParamIndex = $index;
                continue;
            }

            if ($parameter->getAttributes(CurrentUser::class) !== []) {
                $currentUserParams[] = ['index' => $index];
            }
        }

        $request = $this->request;

        return static function () use ($subscriber, $methodName, $handler, $grants, $requestParamIndex, $currentUserParams, $request, $checker): void {
            try {
                if ($handler->checkReferer !== null) {
                    check_ajax_referer($handler->checkReferer);
                }

                if ($grants !== []) {
                    try {
                        $checker->check($grants);
                    } catch (AccessDeniedException) {
                        throw new ForbiddenException('Insufficient permissions.');
                    }
                }

                $injections = [];

                if ($requestParamIndex !== null) {
                    $injections[$requestParamIndex] = $request ?? Request::createFromGlobals();
                }

                foreach ($currentUserParams as $param) {
                    $injections[$param['index']] = wp_get_current_user();
                }

                ksort($injections);
                $result = $subscriber->{$methodName}(...array_values($injections));

                if ($result instanceof JsonResponse) {
                    if ($result->statusCode < 400) {
                        wp_send_json_success($result->data, $result->statusCode);
                    } else {
                        wp_send_json_error($result->data, $result->statusCode);
                    }
                }
            } catch (HttpException $e) {
                $message = $e->getStatusCode() < 500
                    ? $e->getMessage()
                    : 'An internal error occurred.';
                wp_send_json_error($message, $e->getStatusCode());
            }
        };
    }
}
