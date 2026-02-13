<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\TfidfVectorizer;
use PHPUnit\Framework\TestCase;

final class TfidfVectorizerTest extends TestCase
{
    public function testFitTransformCreatesExpectedMatrixShape(): void
    {
        $docs = [
            'machine learning in php',
            'php library for machine intelligence',
            'learning systems and intelligence',
        ];

        $vectorizer = new TfidfVectorizer();
        $matrix = $vectorizer->fitTransform($docs);
        $vocab = $vectorizer->getVocabulary();

        self::assertCount(3, $matrix);
        self::assertNotEmpty($vocab);
        self::assertCount(count($vocab), $matrix[0]);
    }
}
