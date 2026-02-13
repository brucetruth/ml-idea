<?php

declare(strict_types=1);

namespace ML\IDEA\Model;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Exceptions\SerializationException;

final class ModelSerializer
{
    public static function save(PersistableModelInterface $model, string $path): void
    {
        $payload = [
            'class' => $model::class,
            'state' => $model->toArray(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json) === false) {
            throw new SerializationException(sprintf('Failed to write model to path: %s', $path));
        }
    }

    public static function load(string $path): PersistableModelInterface
    {
        if (!is_file($path)) {
            throw new SerializationException(sprintf('Model file not found: %s', $path));
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new SerializationException(sprintf('Failed to read model file: %s', $path));
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $class = $payload['class'] ?? null;
        $state = $payload['state'] ?? null;

        if (!is_string($class) || !is_array($state)) {
            throw new SerializationException('Invalid serialized model payload.');
        }

        if (!class_exists($class)) {
            throw new SerializationException(sprintf('Model class does not exist: %s', $class));
        }

        if (!is_subclass_of($class, PersistableModelInterface::class)) {
            throw new SerializationException(sprintf('Model class must implement %s', PersistableModelInterface::class));
        }

        /** @var class-string<PersistableModelInterface> $class */
        return $class::fromArray($state);
    }
}
