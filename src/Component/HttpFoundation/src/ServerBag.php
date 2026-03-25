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

namespace WpPack\Component\HttpFoundation;

class ServerBag extends ParameterBag
{
    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->parameters as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = (string) $value;
            }
        }

        if (isset($this->parameters['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $this->parameters['CONTENT_TYPE'];
        }

        if (isset($this->parameters['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $this->parameters['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
