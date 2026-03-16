<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger;

use WpPack\Component\Messenger\Stamp\StampInterface;

final class Envelope
{
    /** @var array<class-string<StampInterface>, list<StampInterface>> */
    private array $stamps;

    /**
     * @param array<StampInterface> $stamps
     */
    private function __construct(
        private readonly object $message,
        array $stamps = [],
    ) {
        $this->stamps = [];
        foreach ($stamps as $stamp) {
            $this->stamps[$stamp::class][] = $stamp;
        }
    }

    /**
     * @param array<StampInterface> $stamps
     */
    public static function wrap(object $message, array $stamps = []): self
    {
        if ($message instanceof self) {
            return $message->with(...$stamps);
        }

        return new self($message, $stamps);
    }

    public function with(StampInterface ...$stamps): self
    {
        $clone = clone $this;
        foreach ($stamps as $stamp) {
            $clone->stamps[$stamp::class][] = $stamp;
        }

        return $clone;
    }

    /**
     * @param class-string<StampInterface> $stampClass
     */
    public function withoutAll(string $stampClass): self
    {
        $clone = clone $this;
        unset($clone->stamps[$stampClass]);

        return $clone;
    }

    public function getMessage(): object
    {
        return $this->message;
    }

    /**
     * @template T of StampInterface
     *
     * @param class-string<T> $stampClass
     *
     * @return T|null
     */
    public function last(string $stampClass): ?StampInterface
    {
        $stamps = $this->stamps[$stampClass] ?? [];

        return $stamps[array_key_last($stamps)] ?? null;
    }

    /**
     * @template T of StampInterface
     *
     * @param class-string<T>|null $stampClass
     *
     * @return list<T>
     */
    public function all(?string $stampClass = null): array
    {
        if ($stampClass === null) {
            return array_merge(...array_values($this->stamps) ?: [[]]);
        }

        return $this->stamps[$stampClass] ?? [];
    }
}
