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
use WpPack\Component\Scim\Schema\ScimConstants;

final readonly class PatchRequest
{
    /** @var list<PatchOperation> */
    public array $operations;

    /**
     * @param list<PatchOperation> $operations
     */
    public function __construct(array $operations)
    {
        $this->operations = $operations;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        $schemas = $body['schemas'] ?? [];
        if (!\in_array(ScimConstants::PATCH_OP_SCHEMA, $schemas, true)) {
            throw new InvalidPatchException('Request must include PatchOp schema.');
        }

        $operations = [];
        foreach ($body['Operations'] ?? [] as $op) {
            if (!\is_array($op)) {
                throw new InvalidPatchException('Each operation must be a JSON object.');
            }

            $opName = strtolower($op['op'] ?? '');
            if (!\in_array($opName, ['add', 'replace', 'remove'], true)) {
                throw new InvalidPatchException(sprintf('Invalid patch operation: "%s".', $op['op'] ?? ''));
            }

            $operations[] = new PatchOperation(
                op: $opName,
                path: $op['path'] ?? null,
                value: $op['value'] ?? null,
            );
        }

        if ($operations === []) {
            throw new InvalidPatchException('PatchOp must contain at least one operation.');
        }

        return new self($operations);
    }
}
