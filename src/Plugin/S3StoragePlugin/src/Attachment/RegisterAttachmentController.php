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

namespace WPPack\Plugin\S3StoragePlugin\Attachment;

use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Media\AttachmentManagerInterface;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;

#[RestRoute(route: '/s3/register-attachment', methods: HttpMethod::POST, namespace: 'wppack/v1')]
#[IsGranted('upload_files')]
final class RegisterAttachmentController extends AbstractRestController
{
    public function __construct(
        private readonly AttachmentRegistrar $registrar,
        private readonly StorageAdapterInterface $adapter,
        private readonly AttachmentManagerInterface $attachment,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $key = $payload->getString('key');

        if ($key === '') {
            return $this->json(['error' => 'The "key" parameter is required.'], 400);
        }

        if ($this->registrar->isResizedImage($key)) {
            return $this->json(['error' => 'Resized images cannot be registered as attachments.'], 400);
        }

        if (!$this->adapter->fileExists($key)) {
            return $this->json(['error' => sprintf('File "%s" does not exist in storage.', $key)], 404);
        }

        $attachmentId = $this->registrar->register($key, $this->getUserId());

        if ($attachmentId === null) {
            return $this->json(['error' => 'Failed to register attachment.'], 500);
        }

        $attachment = $this->attachment->prepareForJs($attachmentId);

        if ($attachment === null) {
            return $this->json(['error' => 'Failed to prepare attachment data.'], 500);
        }

        return $this->created($attachment);
    }
}
