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

namespace WPPack\Component\Rest;

use WPPack\Component\Rest\Attribute\Param;

/** @internal */
final class RestParamEntry
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $required,
        public readonly mixed $default,
        public readonly ?Param $param,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArgs(): array
    {
        $args = [
            'type' => $this->type,
        ];

        if ($this->required) {
            $args['required'] = true;
        } else {
            $args['required'] = false;
            if ($this->default !== null) {
                $args['default'] = $this->default;
            }
        }

        if ($this->param === null) {
            return $args;
        }

        if ($this->param->description !== null) {
            $args['description'] = $this->param->description;
        }

        if ($this->param->enum !== null) {
            $args['enum'] = $this->param->enum;
        }

        if ($this->param->minimum !== null) {
            $args['minimum'] = $this->param->minimum;
        }

        if ($this->param->maximum !== null) {
            $args['maximum'] = $this->param->maximum;
        }

        if ($this->param->minLength !== null) {
            $args['minLength'] = $this->param->minLength;
        }

        if ($this->param->maxLength !== null) {
            $args['maxLength'] = $this->param->maxLength;
        }

        if ($this->param->pattern !== null) {
            $args['pattern'] = $this->param->pattern;
        }

        if ($this->param->format !== null) {
            $args['format'] = $this->param->format;
        }

        if ($this->param->items !== null) {
            $args['items'] = ['type' => $this->param->items];
        }

        return $args;
    }
}
