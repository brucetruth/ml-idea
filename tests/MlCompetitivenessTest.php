<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Calibration\CalibratedClassifierCV;
use ML\IDEA\Classifiers\LinearSVC;
use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Ensemble\GradientBoostingClassifier;
use ML\IDEA\Ensemble\RandomForestClassifier;
use ML\IDEA\Ensemble\RandomForestRegressor;
use ML\IDEA\Explain\PermutationImportance;
use ML\IDEA\Metrics\ClusteringMetrics;
use ML\IDEA\Model\ModelSerializer;
use ML\IDEA\Model\PipelineSerializer;
use ML\IDEA\ModelSelection\GridSearchClassifier;
use ML\IDEA\ModelSelection\RandomizedSearchClassifier;
use ML\IDEA\Pipeline\PipelineClassifier;
use ML\IDEA\Pipeline\TabularPipelineClassifier;
use ML\IDEA\Preprocessing\OneHotEncoder;
use ML\IDEA\Preprocessing\StandardScaler;
use ML\IDEA\Regression\LinearRegression;
use PHPUnit\Framework\TestCase;

final class MlCompetitivenessTest extends TestCase
{
    public function testRandomForestUsesDeepTreesAndPredictProba(): void
    {
        $samples = [[1, 1], [1, 2], [2, 1], [4, 4], [5, 5], [4, 5]];
        $labels = ['A', 'A', 'A', 'B', 'B', 'B'];

        $rf = new RandomForestClassifier(nEstimators: 25, maxDepth: 3, seed: 42);
        $rf->train($samples, $labels);

        self::assertSame('B', $rf->predict([4.8, 4.8]));
        $proba = $rf->predictProba([4.8, 4.8]);
        self::assertArrayHasKey('B', $proba);
        self::assertGreaterThan(0.5, $proba['B']);

        $roundTrip = RandomForestClassifier::fromArray($rf->toArray());
        self::assertSame('B', $roundTrip->predict([4.8, 4.8]));
    }

    public function testGradientBoostingAndLinearSVC(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [3, 3], [3, 4], [4, 3]];
        $labels = [0, 0, 0, 1, 1, 1];

        $gb = new GradientBoostingClassifier(nEstimators: 40, learningRate: 0.2, maxDepth: 2, seed: 42);
        $gb->train($samples, $labels);
        self::assertSame(1, $gb->predict([3.5, 3.5]));

        $svc = new LinearSVC(iterations: 500);
        $svc->train($samples, $labels);
        self::assertSame(1, $svc->predict([3.5, 3.5]));
    }

    public function testPipelineSerializationRoundTrip(): void
    {
        $samples = [[1.0, 2.0], [2.0, 3.0], [8.0, 9.0], [9.0, 10.0]];
        $labels = [0, 0, 1, 1];

        $pipeline = new PipelineClassifier(
            [new StandardScaler()],
            new LogisticRegression(iterations: 300, batchSize: 2),
        );
        $pipeline->train($samples, $labels);

        $path = sys_get_temp_dir() . '/ml-idea-pipeline-' . uniqid('', true) . '.json';
        PipelineSerializer::save($pipeline, $path);
        $loaded = PipelineSerializer::loadClassifier($path);
        self::assertSame($pipeline->predict([9.0, 10.0]), $loaded->predict([9.0, 10.0]));
        @unlink($path);
    }

    public function testTabularPipelineWithOneHotEncoder(): void
    {
        $samples = [['red', 1], ['red', 2], ['blue', 8], ['blue', 9]];
        $labels = [0, 0, 1, 1];

        $pipeline = new TabularPipelineClassifier(
            new OneHotEncoder(),
            [new StandardScaler()],
            new LogisticRegression(iterations: 400),
        );
        $pipeline->train($samples, $labels);
        self::assertSame(1, $pipeline->predict(['blue', 9]));

        $roundTrip = TabularPipelineClassifier::fromArray($pipeline->toArray());
        self::assertSame(1, $roundTrip->predict(['blue', 9]));
    }

    public function testGridSearchUsesStratifiedCvAndRandomizedSearch(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [3, 3], [3, 4], [4, 3], [0.5, 0.5], [3.5, 3.5]];
        $labels = [0, 0, 0, 1, 1, 1, 0, 1];

        $grid = new GridSearchClassifier(
            static fn (array $params): LogisticRegression => new LogisticRegression(iterations: (int) ($params['iterations'] ?? 200)),
            stratified: true,
        );
        $grid->fit($samples, $labels, ['iterations' => [200, 400]]);
        self::assertNotEmpty($grid->bestParams());

        $random = new RandomizedSearchClassifier(
            static fn (array $params): LogisticRegression => new LogisticRegression(iterations: (int) ($params['iterations'] ?? 200)),
            seed: 42,
        );
        $random->fit($samples, $labels, ['iterations' => [200, 400, 600]], 2);
        self::assertNotEmpty($random->bestParams());
    }

    public function testIsotonicCalibration(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [3, 3], [3, 4], [4, 3]];
        $labels = [0, 0, 0, 1, 1, 1];

        $base = new LogisticRegression(iterations: 500);
        $calibrated = new CalibratedClassifierCV($base, cv: 3, method: 'isotonic');
        $calibrated->train($samples, $labels);
        $proba = $calibrated->predictProba([3.5, 3.5]);
        self::assertGreaterThan(0.5, array_values($proba)[1] ?? 0.0);
    }

    public function testLinearRegressionPersistence(): void
    {
        $samples = [[1.0], [2.0], [3.0], [4.0]];
        $targets = [2.0, 4.0, 6.0, 8.0];

        $model = new LinearRegression(iterations: 1000);
        $model->train($samples, $targets);

        $path = sys_get_temp_dir() . '/ml-idea-lr-' . uniqid('', true) . '.json';
        ModelSerializer::save($model, $path);
        $loaded = ModelSerializer::load($path);
        self::assertInstanceOf(LinearRegression::class, $loaded);
        self::assertEqualsWithDelta(10.0, $loaded->predict([5.0]), 1.5);
        @unlink($path);
    }

    public function testRandomForestRegressorPersistence(): void
    {
        $samples = [[1.0], [2.0], [3.0], [4.0]];
        $targets = [2.0, 4.0, 6.0, 8.0];

        $model = new RandomForestRegressor(nEstimators: 20, maxDepth: 3, seed: 42);
        $model->train($samples, $targets);
        $roundTrip = RandomForestRegressor::fromArray($model->toArray());
        self::assertEqualsWithDelta($model->predict([2.5]), $roundTrip->predict([2.5]), 0.01);
    }

    public function testPermutationImportanceRanksInformativeFeature(): void
    {
        $samples = [[0, 10], [0, 11], [1, 20], [1, 21], [0, 12], [1, 19]];
        $labels = [0, 0, 1, 1, 0, 1];

        $model = new LogisticRegression(iterations: 600);
        $model->train($samples, $labels);

        $importances = PermutationImportance::forClassifier($model, $samples, $labels, nRepeats: 5, seed: 42);
        self::assertGreaterThan($importances[1], $importances[0]);
    }

    public function testSilhouetteScoreForWellSeparatedClusters(): void
    {
        $samples = [[1, 1], [1.2, 0.8], [5, 5], [5.2, 4.8], [9, 1], [9.1, 0.9]];
        $labels = [0, 0, 1, 1, 2, 2];

        $score = ClusteringMetrics::silhouetteScore($samples, $labels);
        self::assertGreaterThan(0.3, $score);
    }
}
