<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax;

use WpPack\Component\Ajax\Attribute\Ajax;
use WpPack\Component\HttpFoundation\Exception\ForbiddenException;
use WpPack\Component\HttpFoundation\Exception\HttpException;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\Authorization\IsGrantedChecker;
use WpPack\Component\Role\Exception\AccessDeniedException;
use WpPack\Component\Security\Security;

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
                wp_send_json_error($e->getMessage(), $e->getStatusCode());
            }
        };
    }
}
