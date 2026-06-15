<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Embeddings;

use ML\IDEA\Vision\Contracts\VisionEmbedderInterface;
use ML\IDEA\Vision\Features\ImageFeatureVectorizer;
use ML\IDEA\Vision\Features\ImageForensicsFeatureExtractor;

/** Local image embeddings from forensics feature vectors (no neural network required). */
final class ForensicsVisionEmbedder implements VisionEmbedderInterface
{
    public function __construct(
        private readonly ImageForensicsFeatureExtractor $features = new ImageForensicsFeatureExtractor(),
        private readonly ImageFeatureVectorizer $vectorizer = new ImageFeatureVectorizer(),
    ) {
    }

    public function embedImage(string $path): array
    {
        return $this->vectorizer->transform($this->features->fromImageFile($path));
    }

    public function embedBatch(array $paths): array
    {
        $vectors = [];
        foreach ($paths as $path) {
            $vectors[] = $this->embedImage($path);
        }

        return $vectors;
    }

    public function dimensions(): int
    {
        return count($this->vectorizer->featureNames());
    }
}
