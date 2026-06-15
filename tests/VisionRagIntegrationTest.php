<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Embeddings\TeiEmbedder;
use ML\IDEA\RAG\Embeddings\VisionPathEmbedder;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\Vision\Classifiers\AuthenticityClassifier;
use ML\IDEA\Vision\Embeddings\ForensicsVisionEmbedder;
use ML\IDEA\Vision\Eval\VisionEval;
use ML\IDEA\Vision\Support\VisionTestImages;
use PHPUnit\Framework\TestCase;

final class VisionRagIntegrationTest extends TestCase
{
    public function testForensicsVisionEmbedderProducesFixedDimensions(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension not available.');
        }

        $path = sys_get_temp_dir() . '/ml-idea-forensics-embed-' . uniqid('', true) . '.png';
        VisionTestImages::createFlatAiLike($path);

        $embedder = new ForensicsVisionEmbedder();
        $vector = $embedder->embedImage($path);

        self::assertCount($embedder->dimensions(), $vector);
        @unlink($path);
    }

    public function testVisionPathEmbedderIndexesFilePaths(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension not available.');
        }

        $path = sys_get_temp_dir() . '/ml-idea-path-embed-' . uniqid('', true) . '.png';
        VisionTestImages::createTexturedAuthentic($path);

        $ragEmbedder = new VisionPathEmbedder(new ForensicsVisionEmbedder());
        $viaPrefix = $ragEmbedder->embed('image://' . $path);
        $direct = $ragEmbedder->embed($path);

        self::assertSame($viaPrefix, $direct);
        @unlink($path);
    }

    public function testTeiEmbedderParsesOpenAiCompatibleResponse(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return ['data' => [['embedding' => [0.2, 0.4, 0.6]]]];
            }
        };

        $embedder = new TeiEmbedder('local-model', 'http://localhost:8080/v1', null, $http);
        self::assertSame([0.2, 0.4, 0.6], $embedder->embed('hello'));
    }

    public function testClassifierEvalOnSyntheticGdImages(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension not available.');
        }

        $dir = sys_get_temp_dir() . '/ml-idea-vision-eval-' . uniqid('', true);
        mkdir($dir, 0777, true);

        $aiPaths = [];
        $authPaths = [];
        for ($i = 0; $i < 4; $i++) {
            $aiPaths[] = VisionTestImages::createFlatAiLike($dir . '/ai-' . $i . '.png');
            $authPaths[] = VisionTestImages::createTexturedAuthentic($dir . '/auth-' . $i . '.png');
        }

        $trainPaths = array_merge($aiPaths, $authPaths);
        $trainLabels = array_merge(array_fill(0, 4, 1), array_fill(0, 4, 0));

        $extractor = new \ML\IDEA\Vision\Features\ImageForensicsFeatureExtractor();
        $trainSignals = array_map(static fn (string $p): array => $extractor->fromImageFile($p), $trainPaths);

        $classifier = new AuthenticityClassifier();
        $classifier->train($trainSignals, $trainLabels);

        $report = VisionEval::classifierReportFromPaths($classifier, $trainPaths, $trainLabels, $extractor);
        self::assertGreaterThan(0.7, $report['roc_auc']);

        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    public function testFlatAndTexturedImagesEmbedDifferently(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension not available.');
        }

        $dir = sys_get_temp_dir() . '/ml-idea-vision-cmp-' . uniqid('', true);
        mkdir($dir, 0777, true);

        $flat = VisionTestImages::createFlatAiLike($dir . '/flat.png');
        $textured = VisionTestImages::createTexturedAuthentic($dir . '/tex.png');

        $embedder = new ForensicsVisionEmbedder();
        $a = $embedder->embedImage($flat);
        $b = $embedder->embedImage($textured);

        self::assertNotEquals($a, $b);

        @unlink($flat);
        @unlink($textured);
        @rmdir($dir);
    }

    public function testOllamaVisionEmbedderParsesEmbedResponse(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return ['embeddings' => [[0.1, 0.2, 0.3]]];
            }
        };

        $dir = sys_get_temp_dir() . '/ml-idea-ollama-vision-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $path = VisionTestImages::createFlatAiLike($dir . '/img.png');

        $embedder = new \ML\IDEA\Vision\Embeddings\OllamaVisionEmbedder(http: $http);
        self::assertSame([0.1, 0.2, 0.3], $embedder->embedImage($path));

        @unlink($path);
        @rmdir($dir);
    }

    public function testVisionIndexerBuildsAndSearches(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension not available.');
        }

        $dir = sys_get_temp_dir() . '/ml-idea-vindex-' . uniqid('', true);
        mkdir($dir, 0777, true);

        $ai = VisionTestImages::createFlatAiLike($dir . '/ai.png');
        $real = VisionTestImages::createTexturedAuthentic($dir . '/real.png');

        $store = new \ML\IDEA\RAG\VectorStore\InMemoryVectorStore();
        $embedder = new ForensicsVisionEmbedder();
        \ML\IDEA\RAG\Vision\VisionIndexer::index($embedder, $store, ['ai' => $ai, 'real' => $real]);

        $hits = \ML\IDEA\RAG\Vision\VisionIndexer::searchByImage($embedder, $store, $ai, k: 1);
        self::assertSame('ai', $hits[0]['id']);

        @unlink($ai);
        @unlink($real);
        @rmdir($dir);
    }
}
