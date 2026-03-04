<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

final class SectionDefinition
{
    /** @var list<FieldDefinition> */
    private array $fields = [];

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?\Closure $renderCallback = null,
    ) {}

    /**
     * @param array<string, mixed> $args
     */
    public function field(string $id, string $title, \Closure $renderCallback, array $args = []): self
    {
        $this->fields[] = new FieldDefinition($id, $title, $renderCallback, $args);

        return $this;
    }

    /**
     * @return list<FieldDefinition>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
