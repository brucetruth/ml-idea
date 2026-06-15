<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Backends;

use ML\IDEA\Vision\Contracts\VisionFeatureBackendInterface;

/** Wrap a callable for ViT/CLIP/custom vision feature backends. */
final class CallableVisionBackend implements VisionFeatureBackendInterface
{
    /** @param callable(array<string, float|int|bool|string>, string): array<string, float|int|bool|string> $processor */
    public function __construct(private $processor)
    {
    }

    public function enrichSignals(array $signals, string $path): array
    {
        return ($this->processor)($signals, $path);
    }
}
