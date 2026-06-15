<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Contracts;

/** Hook for ViT/CLIP/custom neural feature enrichment after forensics extraction. */
interface VisionFeatureBackendInterface
{
    /**
     * @param array<string, float|int|bool|string> $signals
     * @return array<string, float|int|bool|string>
     */
    public function enrichSignals(array $signals, string $path): array;
}
