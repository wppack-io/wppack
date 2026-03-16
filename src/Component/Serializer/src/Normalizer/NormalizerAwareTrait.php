<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

trait NormalizerAwareTrait
{
    private NormalizerInterface $normalizer;

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }
}
