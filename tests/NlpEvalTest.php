<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Registry\DatasetIntegrity;
use ML\IDEA\NLP\Eval\NlpEval;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use PHPUnit\Framework\TestCase;

final class NlpEvalTest extends TestCase
{
    public function testTokenAccuracyAndMacroF1(): void
    {
        $truth = [
            ['NOUN', 'VERB', 'NOUN'],
            ['DET', 'NOUN'],
        ];
        $pred = [
            ['NOUN', 'VERB', 'NOUN'],
            ['DET', 'ADJ'],
        ];

        self::assertSame(0.8, NlpEval::tokenAccuracy(['NOUN', 'VERB', 'NOUN', 'DET', 'NOUN'], ['NOUN', 'VERB', 'NOUN', 'DET', 'ADJ']));
        self::assertGreaterThan(0.0, NlpEval::tokenMacroF1($truth, $pred));
        self::assertArrayHasKey('string:"NOUN"', NlpEval::tokenLabelReport($truth, $pred));
    }

    public function testSentimentReportReturnsAccuracyAndConfusion(): void
    {
        $analyzer = new SentimentAnalyzer();
        $analyzer->train(
            ['great amazing product', 'okay average item', 'awful terrible product'],
            ['positive', 'neutral', 'negative'],
        );

        $result = NlpEval::sentimentReport(
            ['great amazing product', 'awful terrible product', 'okay average item'],
            ['positive', 'negative', 'neutral'],
            $analyzer,
        );

        self::assertGreaterThan(0.5, $result['accuracy']);
        self::assertArrayHasKey('report', $result);
        self::assertArrayHasKey('confusion', $result);
    }

    public function testEntitySpanScoresMatchExactLabelAndText(): void
    {
        $truth = [
            new Entity('Paris', 'CITY', 0, 5),
            new Entity('Alice', 'PERSON', 10, 15),
        ];
        $pred = [
            new Entity('Paris', 'CITY', 0, 5),
            new Entity('Alice', 'ORG', 10, 15),
        ];

        $scores = NlpEval::entitySpanScores($truth, $pred);
        self::assertSame(0.5, $scores['precision']);
        self::assertSame(0.5, $scores['recall']);
        self::assertSame(0.5, $scores['f1']);
    }
}
