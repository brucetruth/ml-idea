<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Classifiers;

use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Preprocessing\StandardScaler;
use ML\IDEA\Vision\Features\ImageFeatureVectorizer;
use ML\IDEA\Vision\Features\ImageForensicsFeatureExtractor;

/** Trainable authenticity classifier on forensics feature vectors (beyond pure heuristics). */
final class AuthenticityClassifier implements PersistableModelInterface
{
    private bool $trained = false;
    private int|float|string|bool $aiClass = 1;
    private int|float|string|bool $authenticClass = 0;

    public function __construct(
        private readonly ImageForensicsFeatureExtractor $features = new ImageForensicsFeatureExtractor(),
        private readonly ImageFeatureVectorizer $vectorizer = new ImageFeatureVectorizer(),
        private readonly StandardScaler $scaler = new StandardScaler(),
        private readonly LogisticRegression $classifier = new LogisticRegression(iterations: 1200),
    ) {
    }

    /**
     * @param array<int, array<string, float|int|bool|string>> $signalSets
     * @param array<int, int|float|string|bool> $labels 1 = ai-generated, 0 = authentic
     */
    public function train(array $signalSets, array $labels): void
    {
        if ($signalSets === [] || count($signalSets) !== count($labels)) {
            throw new InvalidArgumentException('signalSets and labels must be non-empty and equal length.');
        }

        $classes = array_values(array_unique($labels, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('AuthenticityClassifier requires binary labels (0=authentic, 1=ai).');
        }

        sort($classes);
        $this->authenticClass = $classes[0];
        $this->aiClass = $classes[1];

        $samples = array_map(fn (array $signals): array => $this->vectorizer->transform($signals), $signalSets);
        $this->scaler->fit($samples);
        $this->classifier->train($this->scaler->transform($samples), $labels);
        $this->trained = true;
    }

    /**
     * @param array<string, float|int|bool|string> $signals
     * @return array{label: int|float|string|bool, ai_probability: float, authentic_probability: float}
     */
    public function predictSignals(array $signals): array
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('AuthenticityClassifier has not been trained yet.');
        }

        $sample = $this->scaler->transform([$this->vectorizer->transform($signals)])[0];
        $label = $this->classifier->predict($sample);
        $proba = $this->classifier->predictProba($sample);

        return [
            'label' => $label,
            'ai_probability' => (float) ($proba[$this->aiClass] ?? 0.0),
            'authentic_probability' => (float) ($proba[$this->authenticClass] ?? 0.0),
        ];
    }

    /**
     * @return array{label: int|float|string|bool, ai_probability: float, authentic_probability: float, signals: array<string, float|int|bool|string>}
     */
    public function predictFile(string $path, int $maxSamples = 5000): array
    {
        $signals = $this->features->fromImageFile($path, $maxSamples);
        $prediction = $this->predictSignals($signals);

        return array_merge($prediction, ['signals' => $signals]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => 'authenticity_classifier',
            'ai_class' => $this->aiClass,
            'authentic_class' => $this->authenticClass,
            'scaler' => $this->scaler->toArray(),
            'classifier' => $this->classifier->toArray(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        $classifierState = $data['classifier'] ?? null;
        $scalerState = $data['scaler'] ?? null;
        if (!is_array($classifierState) || !is_array($scalerState)) {
            throw new InvalidArgumentException('Invalid AuthenticityClassifier payload.');
        }

        $instance = new self(
            scaler: StandardScaler::fromArray($scalerState),
            classifier: LogisticRegression::fromArray($classifierState),
        );
        $instance->aiClass = $data['ai_class'] ?? 1;
        $instance->authenticClass = $data['authentic_class'] ?? 0;
        $instance->trained = true;

        return $instance;
    }
}
