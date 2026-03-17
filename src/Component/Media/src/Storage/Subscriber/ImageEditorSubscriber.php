<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Media\Attribute\Filter\WpImageEditorsFilter;

#[AsHookSubscriber]
final readonly class ImageEditorSubscriber
{
    /**
     * @param class-string $imageEditorClass FQCN of StorageImageEditor
     */
    public function __construct(
        private string $imageEditorClass,
    ) {}

    /**
     * Prepend StorageImageEditor to the editors list.
     *
     * @param list<class-string> $editors
     * @return list<class-string>
     */
    #[WpImageEditorsFilter(priority: 9)]
    public function filterEditors(array $editors): array
    {
        array_unshift($editors, $this->imageEditorClass);

        return $editors;
    }
}
