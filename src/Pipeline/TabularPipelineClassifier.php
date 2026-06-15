<?php

declare(strict_types=1);

namespace ML\IDEA\Pipeline;

use ML\IDEA\Classifiers\AbstractClassifier;
use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Contracts\TransformerInterface;
use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\Model\PipelineSerializer;
use ML\IDEA\Preprocessing\OneHotEncoder;
use ML\IDEA\Support\Assert;

/** Pipeline for mixed categorical tabular features + numeric transformers. */
final class TabularPipelineClassifier extends AbstractClassifier implements PersistableModelInterface
{
    /** @var array<int, TransformerInterface> */
    private array $transformers;

    /**
     * @param array<int, TransformerInterface> $transformers
     */
    public function __construct(
        private OneHotEncoder $encoder,
        array $transformers,
        private readonly ClassifierInterface $classifier,
    ) {
        $this->transformers = $transformers;
    }

    /**
     * @param array<int, array<int, string|int|float|bool>> $samples
     * @param array<int, int|float|string|bool> $labels
     */
    public function train(array $samples, array $labels): void
    {
        if ($samples === [] || count($samples) !== count($labels)) {
            throw new \InvalidArgumentException('Samples and labels must be non-empty and same length.');
        }

        $x = $this->encoder->fitTransform($samples);
        foreach ($this->transformers as $transformer) {
            $x = $transformer->fitTransform($x);
        }

        $this->classifier->train($x, $labels);
    }

    /** @param array<int, string|int|float|bool> $sample */
    public function predict(array $sample): int|float|string|bool
    {
        $matrix = $this->encoder->transform([$sample]);
        foreach ($this->transformers as $transformer) {
            $matrix = $transformer->transform($matrix);
        }

        return $this->classifier->predict($matrix[0]);
    }

    /** @param array<int, array<int, string|int|float|bool>> $samples */
    public function predictBatch(array $samples): array
    {
        $matrix = $this->encoder->transform($samples);
        foreach ($this->transformers as $transformer) {
            $matrix = $transformer->transform($matrix);
        }

        return $this->classifier->predictBatch($matrix);
    }

    public function encoder(): OneHotEncoder
    {
        return $this->encoder;
    }

    /** @return array<int, TransformerInterface> */
    public function transformers(): array
    {
        return $this->transformers;
    }

    public function classifier(): ClassifierInterface
    {
        return $this->classifier;
    }

    public function toArray(): array
    {
        return [
            'type' => 'tabular',
            'encoder' => ['class' => $this->encoder::class, 'state' => $this->encoder->toArray()],
            'transformers' => PipelineSerializer::exportTransformers($this->transformers),
            'estimator' => [
                'class' => $this->classifier::class,
                'state' => self::requirePersistable($this->classifier)->toArray(),
            ],
        ];
    }

    public static function fromArray(array $data): static
    {
        $encoderEntry = $data['encoder'] ?? null;
        if (!is_array($encoderEntry)) {
            throw new SerializationException('Tabular pipeline missing encoder state.');
        }
        $encoder = OneHotEncoder::fromArray(is_array($encoderEntry['state'] ?? null) ? $encoderEntry['state'] : []);

        $transformers = [];
        foreach ($data['transformers'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $transformer = PipelineSerializer::loadPersistable($entry);
            if (!$transformer instanceof TransformerInterface) {
                throw new SerializationException('Invalid transformer in tabular pipeline.');
            }
            $transformers[] = $transformer;
        }

        $estimatorEntry = $data['estimator'] ?? $data['classifier'] ?? null;
        if (!is_array($estimatorEntry)) {
            throw new SerializationException('Tabular pipeline missing estimator state.');
        }
        $estimator = PipelineSerializer::loadPersistable($estimatorEntry);
        if (!$estimator instanceof ClassifierInterface) {
            throw new SerializationException('Tabular pipeline estimator must be a classifier.');
        }

        return new self($encoder, $transformers, $estimator);
    }

    private static function requirePersistable(object $model): PersistableModelInterface
    {
        if (!$model instanceof PersistableModelInterface) {
            throw new SerializationException(sprintf('Model %s must implement PersistableModelInterface.', $model::class));
        }

        return $model;
    }
}
