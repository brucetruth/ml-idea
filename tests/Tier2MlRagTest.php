<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Calibration\CalibratedClassifierCV;
use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Clustering\DBSCAN;
use ML\IDEA\Clustering\KMeans;
use ML\IDEA\Clustering\MiniBatchKMeans;
use ML\IDEA\Data\SparseVector;
use ML\IDEA\Metrics\ClusteringMetrics;
use ML\IDEA\NLP\Backends\HuggingFaceInferenceBackend;
use ML\IDEA\NLP\Backends\OllamaNlpBackend;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\TfidfVectorizer;
use ML\IDEA\NLP\Vectorize\HashingVectorizer;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\VectorStore\AnnVectorStore;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;
use ML\IDEA\RAG\VectorStore\SQLiteAnnVectorStore;
use ML\IDEA\RAG\VectorStore\SQLiteVec0VectorStore;
use ML\IDEA\RAG\VectorStore\VectorStoreFactory;
use ML\IDEA\Vision\Backends\CallableVisionBackend;
use ML\IDEA\Vision\Classifiers\AuthenticityClassifier;
use ML\IDEA\Vision\Eval\VisionEval;
use ML\IDEA\Vision\Features\ImageForensicsFeatureExtractor;
use PHPUnit\Framework\TestCase;

final class Tier2MlRagTest extends TestCase
{
    public function testSparseTfidfOutputAndDensify(): void
    {
        $docs = ['alpha beta', 'beta gamma', 'alpha gamma delta'];
        $vectorizer = new TfidfVectorizer(outputSparse: true);
        $sparse = $vectorizer->fitTransform($docs);

        self::assertTrue($vectorizer->outputsSparse());
        self::assertLessThan(count($vectorizer->getVocabulary()), count($sparse[0]));
        self::assertTrue(SparseVector::isSparseRow($sparse[0], count($vectorizer->getVocabulary())));

        $dense = $vectorizer->densify($sparse);
        self::assertCount(count($vectorizer->getVocabulary()), $dense[0]);
    }

    public function testHashingVectorizerSparseOutput(): void
    {
        $vectorizer = new HashingVectorizer(dimensions: 128, outputSparse: true);
        $sparse = $vectorizer->fitTransform(['hello world', 'world of php']);

        self::assertTrue($vectorizer->outputsSparse());
        self::assertLessThan(128, count($sparse[0]));
        self::assertCount(128, $vectorizer->densify($sparse)[0]);
    }

    public function testMulticlassCalibratedClassifierNormalizesProbabilities(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [2, 2], [3, 3], [3, 4], [4, 3], [4, 4], [2, 3]];
        $labels = ['A', 'A', 'A', 'B', 'B', 'B', 'C', 'C', 'C'];

        $base = new LogisticRegression(iterations: 800);
        $cal = new CalibratedClassifierCV($base, cv: 3, method: 'platt');
        $cal->train($samples, $labels);

        $proba = $cal->predictProba([4, 4]);
        self::assertCount(3, $proba);
        self::assertEqualsWithDelta(1.0, array_sum($proba), 0.01);
        self::assertSame('C', $cal->predict([4, 4]));
    }

    public function testKMeansSeparatesClusters(): void
    {
        $samples = [[0, 0], [0.1, 0.2], [5, 5], [5.1, 4.9]];
        $kmeans = new KMeans(k: 2, maxIterations: 50, seed: 42);
        $kmeans->fit($samples);

        self::assertNotSame($kmeans->predict([0, 0]), $kmeans->predict([5, 5]));
    }

    public function testDbscanFindsNoiseAndClusters(): void
    {
        $samples = [
            [0, 0], [0.1, 0], [0, 0.1],
            [5, 5], [5.1, 5], [5, 5.1],
            [10, 10],
        ];

        $db = new DBSCAN(eps: 0.25, minSamples: 2);
        $labels = $db->fitPredict($samples);

        self::assertContains(-1, $labels);
        self::assertGreaterThanOrEqual(2, count(array_unique(array_filter($labels, static fn (int $l): bool => $l >= 0))));
    }

    public function testDbscanImplementsClustererInterface(): void
    {
        $samples = [[0, 0], [0.1, 0], [5, 5], [5.1, 5], [10, 10]];
        $db = new DBSCAN(eps: 0.3, minSamples: 2);
        $db->fit($samples);

        self::assertSame(-1, $db->predict([10, 10]));
        self::assertCount(count($samples), $db->predictBatch($samples));
    }

    public function testAnnVectorStoreReturnsTopMatch(): void
    {
        $store = new AnnVectorStore(nlist: 2, nprobe: 2, minItemsForAnn: 4, seed: 42);
        $items = [];
        for ($i = 0; $i < 20; $i++) {
            $items[] = [
                'id' => 'doc-' . $i,
                'vector' => [$i * 0.1, 1.0 - ($i * 0.01)],
                'text' => 'document ' . $i,
                'metadata' => ['bucket' => $i % 2 === 0 ? 'even' : 'odd'],
            ];
        }
        $store->upsert($items);

        $hits = $store->search([1.9, 0.81], k: 3);
        self::assertNotEmpty($hits);
        self::assertSame('doc-19', $hits[0]['id']);
    }

    public function testAnnVectorStoreMatchesExactOnSmallCorpus(): void
    {
        $exact = new InMemoryVectorStore();
        $ann = new AnnVectorStore(nlist: 2, nprobe: 2, minItemsForAnn: 100, seed: 42);

        $items = [
            ['id' => 'a', 'vector' => [1, 0], 'text' => 'a', 'metadata' => []],
            ['id' => 'b', 'vector' => [0, 1], 'text' => 'b', 'metadata' => []],
        ];
        $exact->upsert($items);
        $ann->upsert($items);

        $query = [0.9, 0.1];
        self::assertSame($exact->search($query, 1)[0]['id'], $ann->search($query, 1)[0]['id']);
    }

    public function testOllamaNlpBackendMergesEntities(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    'response' => json_encode([
                        'entities' => [
                            ['text' => 'Alice', 'label' => 'PERSON', 'start' => 0, 'end' => 5],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            }
        };

        $backend = new OllamaNlpBackend(http: $http);
        $draft = new Doc('Alice works here');
        $doc = $backend->process('Alice works here', $draft);

        self::assertCount(1, $doc->ents);
        self::assertSame('Alice', $doc->ents[0]->text);
        self::assertSame('PERSON', $doc->ents[0]->label);
    }

    public function testHuggingFaceInferenceBackendParsesTokenClassification(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [
                    [
                        ['entity_group' => 'PER', 'word' => 'Alice', 'score' => 0.99, 'start' => 0, 'end' => 5],
                        ['entity_group' => 'ORG', 'word' => 'ACME', 'score' => 0.95, 'start' => 12, 'end' => 16],
                    ],
                ];
            }
        };

        $backend = new HuggingFaceInferenceBackend(model: 'dslim/bert-base-NER', apiToken: 'test', http: $http);
        $draft = new Doc('Alice works at ACME');
        $doc = $backend->process('Alice works at ACME', $draft);

        self::assertCount(2, $doc->ents);
        self::assertSame('Alice', $doc->ents[0]->text);
        self::assertSame('PER', $doc->ents[0]->label);
        self::assertSame('huggingface', $doc->attrs['backend'] ?? null);
    }

    public function testAuthenticityClassifierTrainsAndPredicts(): void
    {
        $ai = [
            'generator_hint_score' => 1.0,
            'filename_hint_score' => 0.8,
            'has_exif_camera' => false,
            'flatness_score' => 0.75,
            'color_diversity' => 0.06,
            'clipping_ratio' => 0.35,
            'luma_std' => 12.0,
            'saturation_mean' => 0.4,
            'mean_r' => 128.0,
            'mean_g' => 120.0,
            'mean_b' => 115.0,
        ];
        $authentic = [
            'generator_hint_score' => 0.0,
            'filename_hint_score' => 0.0,
            'has_exif_camera' => true,
            'flatness_score' => 0.2,
            'color_diversity' => 0.25,
            'clipping_ratio' => 0.08,
            'luma_std' => 38.0,
            'saturation_mean' => 0.55,
            'mean_r' => 90.0,
            'mean_g' => 110.0,
            'mean_b' => 80.0,
        ];

        $classifier = new AuthenticityClassifier();
        $trainAi = array_fill(0, 6, $ai);
        $trainAuth = array_fill(0, 6, $authentic);
        $classifier->train(
            array_merge($trainAi, $trainAuth),
            array_merge(array_fill(0, 6, 1), array_fill(0, 6, 0)),
        );

        $result = $classifier->predictSignals($ai);
        self::assertSame(1, $result['label']);
        self::assertGreaterThan(0.5, $result['ai_probability']);

        $roundTrip = AuthenticityClassifier::fromArray($classifier->toArray());
        self::assertSame(1, $roundTrip->predictSignals($ai)['label']);
    }

    public function testKMeansAcceptsSparseInput(): void
    {
        $sparse = [[0 => 1.0, 2 => 0.5], [0 => 0.9, 2 => 0.4], [0 => 0.1, 2 => 0.9], [0 => 0.0, 2 => 1.0]];
        $kmeans = new KMeans(k: 2, seed: 42);
        $kmeans->fit($sparse);

        self::assertNotSame($kmeans->predict([1.0, 0.0, 0.5]), $kmeans->predict([0.0, 0.0, 1.0]));
    }

    public function testSilhouetteWithKMeansLabels(): void
    {
        $samples = [[1, 1], [1.2, 0.8], [5, 5], [5.2, 4.8], [9, 1], [9.1, 0.9]];
        $kmeans = new KMeans(k: 3, seed: 42);
        $kmeans->fit($samples);
        $labels = $kmeans->predictBatch($samples);

        $score = ClusteringMetrics::silhouetteScore($samples, $labels);
        self::assertGreaterThan(0.2, $score);
    }

    public function testMiniBatchKMeansAcceptsSparseInput(): void
    {
        $sparse = [[0 => 1.0, 2 => 0.5], [0 => 0.9, 2 => 0.4], [0 => 0.1, 2 => 0.9], [0 => 0.0, 2 => 1.0]];
        $mb = new MiniBatchKMeans(k: 2, seed: 42);
        $mb->fit($sparse);

        self::assertNotSame($mb->predict([1.0, 0.0, 0.5]), $mb->predict([0.0, 0.0, 1.0]));
    }

    public function testDbscanAcceptsSparseInput(): void
    {
        $sparse = [
            [0 => 0.0, 1 => 0.0],
            [0 => 0.1, 1 => 0.0],
            [0 => 5.0, 1 => 5.0],
            [0 => 5.1, 1 => 5.0],
            [0 => 10.0, 1 => 10.0],
        ];

        $db = new DBSCAN(eps: 0.25, minSamples: 2);
        $labels = $db->fitPredict($sparse);

        self::assertContains(-1, $labels);
    }

    public function testSparseCosineSimilarityMatchesDense(): void
    {
        $denseA = [1.0, 0.0, 0.5];
        $denseB = [0.8, 0.0, 0.6];
        $sparseA = [0 => 1.0, 2 => 0.5];
        $sparseB = [0 => 0.8, 2 => 0.6];

        $denseScore = SparseVector::cosineSimilarity($denseA, $denseB);
        $sparseScore = SparseVector::cosineSimilarity($sparseA, $sparseB);

        self::assertEqualsWithDelta($denseScore, $sparseScore, 1.0e-9);
    }

    public function testInMemoryVectorStoreSearchesSparseVectors(): void
    {
        $store = new InMemoryVectorStore();
        $store->upsert([
            ['id' => 'a', 'vector' => [0 => 1.0, 3 => 0.5], 'text' => 'a', 'metadata' => []],
            ['id' => 'b', 'vector' => [0 => 0.1, 3 => 1.0], 'text' => 'b', 'metadata' => []],
        ]);

        $hits = $store->search([0 => 0.9, 3 => 0.4], k: 1);
        self::assertSame('a', $hits[0]['id']);
    }

    public function testSqliteAnnVectorStorePersistsAndSearches(): void
    {
        $path = sys_get_temp_dir() . '/ml-idea-sqlite-ann-' . uniqid('', true) . '.sqlite';
        $store = new SQLiteAnnVectorStore($path, nlist: 2, nprobe: 2, minItemsForAnn: 4, seed: 42);

        $items = [];
        for ($i = 0; $i < 20; $i++) {
            $items[] = [
                'id' => 'doc-' . $i,
                'vector' => [$i * 0.1, 1.0 - ($i * 0.01)],
                'text' => 'document ' . $i,
                'metadata' => [],
            ];
        }
        $store->upsert($items);

        $hits = $store->search([1.9, 0.81], k: 1);
        self::assertSame('doc-19', $hits[0]['id']);

        $reloaded = new SQLiteAnnVectorStore($path, nlist: 2, nprobe: 2, minItemsForAnn: 4, seed: 42);
        $hits2 = $reloaded->search([1.9, 0.81], k: 1);
        self::assertSame('doc-19', $hits2[0]['id']);

        @unlink($path);
    }

    public function testForensicsExtractorAddsFrequencyFeatures(): void
    {
        $matrix = [];
        for ($y = 0; $y < 32; $y++) {
            $row = [];
            for ($x = 0; $x < 32; $x++) {
                $v = (float) ((($x + $y) % 16) * 16);
                $row[] = [$v, $v, $v];
            }
            $matrix[] = $row;
        }

        $extractor = new ImageForensicsFeatureExtractor();
        $features = $extractor->fromRgbMatrix($matrix);

        self::assertArrayHasKey('dct_high_freq_ratio', $features);
        self::assertArrayHasKey('noise_residual_std', $features);
        self::assertArrayHasKey('patch_variance_std', $features);
        self::assertGreaterThanOrEqual(0.0, (float) $features['dct_high_freq_ratio']);
    }

    public function testCallableVisionBackendEnrichesSignals(): void
    {
        $backend = new CallableVisionBackend(
            static fn (array $signals, string $path): array => array_merge($signals, ['backend_path' => basename($path)]),
        );

        $enriched = $backend->enrichSignals(['mean_r' => 100.0], '/tmp/sample-photo.jpg');
        self::assertSame('sample-photo.jpg', $enriched['backend_path']);
    }

    public function testVectorStoreFactoryFallsBackWithoutVec0(): void
    {
        $path = sys_get_temp_dir() . '/ml-idea-vec0-factory-' . uniqid('', true) . '.sqlite';
        $store = VectorStoreFactory::createPersisted($path, dimensions: 4);

        self::assertInstanceOf(SQLiteVec0VectorStore::class, $store);
        if (!$store->usesVec0()) {
            self::assertFalse(SQLiteVec0VectorStore::isExtensionAvailable());
        }

        $store->upsert([
            ['id' => 'a', 'vector' => [1, 0], 'text' => 'a', 'metadata' => []],
            ['id' => 'b', 'vector' => [0, 1], 'text' => 'b', 'metadata' => []],
        ]);
        self::assertSame('a', $store->search([0.9, 0.1], 1)[0]['id']);

        @unlink($path);
    }

    public function testVisionEvalFixtureWithClassifier(): void
    {
        $fixture = __DIR__ . '/fixtures/vision_authenticity_signals.json';
        [$signals, $labels] = VisionEval::loadSignalFixtures($fixture);

        $classifier = new AuthenticityClassifier();
        $classifier->train($signals, $labels);

        $report = VisionEval::classifierReport($classifier, $signals, $labels);
        self::assertGreaterThan(0.7, $report['roc_auc']);
    }
}
