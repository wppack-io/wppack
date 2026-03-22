<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin;

use WpPack\Component\Option\OptionManager;

final readonly class SuppressionList
{
    private const OPTION_KEY = 'wppack_ses_suppression_list';

    public function __construct(
        private OptionManager $option,
    ) {}

    /**
     * @param list<string> $addresses
     */
    public function add(array $addresses): void
    {
        /** @var string $json */
        $json = $this->option->get(self::OPTION_KEY, '[]');

        /** @var list<string> $list */
        $list = json_decode($json, true) ?: [];

        $updated = false;
        foreach ($addresses as $address) {
            $normalized = strtolower($address);

            if (!\in_array($normalized, $list, true)) {
                $list[] = $normalized;
                $updated = true;
            }
        }

        if ($updated) {
            $this->option->update(self::OPTION_KEY, json_encode($list, \JSON_THROW_ON_ERROR));
        }
    }
}
