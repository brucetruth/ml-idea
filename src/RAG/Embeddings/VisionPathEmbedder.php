<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\Vision\Contracts\VisionEmbedderInterface;

/**
 * Bridge VisionEmbedderInterface into RAG EmbedderInterface.
 * Pass an image file path as the "text" input, or prefix with image://
 */
final class VisionPathEmbedder implements EmbedderInterface
{
    public function __construct(
        private readonly VisionEmbedderInterface $visionEmbedder,
        private readonly bool $requireExistingFile = true,
    ) {
    }

    public function embed(string $text): array
    {
        $path = self::resolvePath($text);
        if ($this->requireExistingFile && !is_file($path)) {
            throw new InvalidArgumentException(sprintf('Vision embedder expected an image file path: %s', $text));
        }

        return $this->visionEmbedder->embedImage($path);
    }

    public function embedBatch(array $texts): array
    {
        $vectors = [];
        foreach ($texts as $text) {
            $vectors[] = $this->embed($text);
        }

        return $vectors;
    }

    public static function resolvePath(string $text): string
    {
        if (str_starts_with($text, 'image://')) {
            return substr($text, 8);
        }

        return $text;
    }
}
