<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Backends;

use ML\IDEA\Vision\Contracts\VisionEmbedderInterface;

/** Wrap a callable for custom neural image embedders. */
final class CallableVisionEmbedder implements VisionEmbedderInterface
{
    /** @param callable(string): array<int, float> $embedder */
    public function __construct(private $embedder)
    {
    }

    public function embedImage(string $path): array
    {
        return ($this->embedder)($path);
    }

    public function embedBatch(array $paths): array
    {
        $vectors = [];
        foreach ($paths as $path) {
            $vectors[] = $this->embedImage($path);
        }

        return $vectors;
    }
}
