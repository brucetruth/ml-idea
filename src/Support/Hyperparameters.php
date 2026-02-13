<?php

declare(strict_types=1);

namespace ML\IDEA\Support;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class Hyperparameters
{
    /** @return array<string, mixed> */
    public static function extract(object $model): array
    {
        $reflection = new \ReflectionClass($model);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $params = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $property = self::findProperty($reflection, $name);

            if ($property !== null) {
                $property->setAccessible(true);
                $params[$name] = $property->getValue($model);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $params[$name] = $parameter->getDefaultValue();
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function cloneWith(object $model, array $overrides): object
    {
        $params = array_merge(self::extract($model), $overrides);
        $reflection = new \ReflectionClass($model);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return clone $model;
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            throw new InvalidArgumentException(sprintf('Missing constructor parameter for cloneWithParams: %s', $name));
        }

        return $reflection->newInstanceArgs($args);
    }

    private static function findProperty(\ReflectionClass $reflection, string $name): ?\ReflectionProperty
    {
        while ($reflection !== false) {
            if ($reflection->hasProperty($name)) {
                return $reflection->getProperty($name);
            }

            $reflection = $reflection->getParentClass();
        }

        return null;
    }
}
