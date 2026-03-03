<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax;

use WpPack\Component\Ajax\Attribute\AjaxHandler;
use WpPack\Component\Ajax\Response\JsonResponse;

final class AjaxHandlerRegistry
{
    public function register(object $subscriber): void
    {
        $reflection = new \ReflectionClass($subscriber);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(AjaxHandler::class);

            foreach ($attributes as $attribute) {
                $handler = $attribute->newInstance();
                $callback = $this->createCallback($subscriber, $method->getName(), $handler);

                if ($handler->access === Access::Public || $handler->access === Access::Authenticated) {
                    add_action("wp_ajax_{$handler->action}", $callback, $handler->priority);
                }

                if ($handler->access === Access::Public || $handler->access === Access::Guest) {
                    add_action("wp_ajax_nopriv_{$handler->action}", $callback, $handler->priority);
                }
            }
        }
    }

    private function createCallback(object $subscriber, string $method, AjaxHandler $handler): \Closure
    {
        return static function () use ($subscriber, $method, $handler): void {
            if ($handler->checkReferer !== null) {
                check_ajax_referer($handler->checkReferer);
            }

            if ($handler->capability !== null && !current_user_can($handler->capability)) {
                $result = JsonResponse::error('Insufficient permissions.', 403);
            } else {
                $result = $subscriber->{$method}();
            }

            if ($result instanceof JsonResponse) {
                $result->send();
            }
        };
    }
}
