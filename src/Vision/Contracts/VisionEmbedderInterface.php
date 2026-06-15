<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Contracts;

/** Neural image embedding hook (ViT/CLIP/local ONNX) for similarity search. */
interface VisionEmbedderInterface
{
    /** @return array<int, float> */
    public function embedImage(string $path): array;

    /** @param array<int, string> $paths @return array<int, array<int, float>> */
    public function embedBatch(array $paths): array;
}
