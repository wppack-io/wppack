<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\Route;
use WpPack\Component\Rest\HttpMethod;

#[Route(route: '/s3/presigned-url', methods: HttpMethod::POST, namespace: 'wppack/v1')]
final class PreSignedUrlController extends AbstractRestController
{
    public function __construct(
        private readonly PreSignedUrlGenerator $generator,
        private readonly UploadPolicy $policy,
    ) {}

    public function __invoke(\WP_REST_Request $request): JsonResponse
    {
        /** @var string|null $filename */
        $filename = $request->get_param('filename');
        /** @var string|null $contentType */
        $contentType = $request->get_param('content_type');
        /** @var int|string|null $contentLength */
        $contentLength = $request->get_param('content_length');

        if ($filename === null || $filename === '') {
            return $this->json(['error' => 'The "filename" parameter is required.'], 400);
        }

        if ($contentType === null || $contentType === '') {
            return $this->json(['error' => 'The "content_type" parameter is required.'], 400);
        }

        if ($contentLength === null) {
            return $this->json(['error' => 'The "content_length" parameter is required.'], 400);
        }

        $contentLength = (int) $contentLength;

        if (!$this->policy->isAllowedType($contentType)) {
            return $this->json(['error' => sprintf('Content type "%s" is not allowed.', $contentType)], 400);
        }

        if (!$this->policy->isAllowedSize($contentLength)) {
            return $this->json([
                'error' => sprintf(
                    'Content length %d exceeds the maximum allowed size of %d bytes.',
                    $contentLength,
                    $this->policy->getMaxFileSize(),
                ),
            ], 400);
        }

        $result = $this->generator->generate($filename, $contentType, $contentLength);

        return $this->json([
            'url' => $result->url,
            'key' => $result->key,
            'expires_in' => $result->expiresIn,
        ]);
    }
}
