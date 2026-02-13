<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Model\ModelSerializer;
use PHPUnit\Framework\TestCase;

final class ModelSerializerTest extends TestCase
{
    public function testCanSaveAndLoadModel(): void
    {
        $samples = [[1, 1], [1, 2], [4, 4], [5, 5]];
        $labels = ['A', 'A', 'B', 'B'];

        $model = new KNearestNeighbors(k: 3, weighted: true);
        $model->train($samples, $labels);

        $path = sys_get_temp_dir() . '/ml_idea_model_' . uniqid('', true) . '.json';
        ModelSerializer::save($model, $path);

        $loaded = ModelSerializer::load($path);
        @unlink($path);

        self::assertInstanceOf(ClassifierInterface::class, $loaded);

        /** @var ClassifierInterface $loaded */
        self::assertSame('B', $loaded->predict([4.3, 4.2]));
    }
}
