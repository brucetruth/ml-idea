<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\LogisticRegression;
use PHPUnit\Framework\TestCase;

final class CoreContractsTest extends TestCase
{
    public function testEstimatorFitAliasAndParamCloningWork(): void
    {
        $model = new LogisticRegression(learningRate: 0.1, iterations: 2000);

        $samples = [[0, 0], [0, 1], [1, 0], [1, 1]];
        $labels = [0, 1, 1, 1];
        $model->fit($samples, $labels);

        self::assertSame(1, $model->predict([1, 1]));

        $params = $model->getParams();
        self::assertArrayHasKey('learningRate', $params);
        self::assertArrayHasKey('iterations', $params);

        $clone = $model->cloneWithParams(['learningRate' => 0.2]);
        $cloneParams = $clone->getParams();
        self::assertSame(0.2, $cloneParams['learningRate']);
    }

    public function testProbabilisticAndSerializableContractsWork(): void
    {
        $model = new LogisticRegression(learningRate: 0.2, iterations: 1500);
        $samples = [[0, 0], [0, 1], [1, 0], [1, 1]];
        $labels = [0, 1, 1, 1];
        $model->train($samples, $labels);

        $proba = $model->predictProba([1, 1]);
        self::assertCount(2, $proba);

        $path = sys_get_temp_dir() . '/ml_idea_lr_' . uniqid('', true) . '.json';
        $model->save($path);
        $loaded = LogisticRegression::load($path);
        @unlink($path);

        self::assertSame($model->predict([1, 1]), $loaded->predict([1, 1]));
    }
}
