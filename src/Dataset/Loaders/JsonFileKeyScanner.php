<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Loaders;

use ML\IDEA\Exceptions\InvalidArgumentException;

/**
 * Stream-read individual JSON object keys from large single-object files
 * without decoding the entire payload into memory.
 */
final class JsonFileKeyScanner
{
    public function __construct(private readonly string $path)
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Dataset file not found: {$path}");
        }
    }

    /** @return array<int, string>|null */
    public function readStringArray(string $key): ?array
    {
        $value = $this->readValue($key);
        if (!is_array($value)) {
            return null;
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function readObject(string $key): ?array
    {
        $value = $this->readValue($key);
        if (!is_array($value)) {
            return null;
        }

        return $value;
    }

    private function readValue(string $key): mixed
    {
        $needle = json_encode($key, JSON_THROW_ON_ERROR) . ':';
        $handle = $this->openAtNeedle($needle);
        if ($handle === null) {
            return null;
        }

        try {
            $this->skipWhitespace($handle);
            $first = fgetc($handle);
            if ($first === false) {
                return null;
            }

            if ($first !== '[' && $first !== '{') {
                return null;
            }

            $json = $first . $this->readBalanced($handle, $first);
            $decoded = json_decode($json, true);

            return is_array($decoded) || is_string($decoded) ? $decoded : null;
        } finally {
            fclose($handle);
        }
    }

    /** @return resource|null */
    private function openAtNeedle(string $needle)
    {
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to read dataset file: {$this->path}");
        }

        $overlap = '';
        $needleLength = strlen($needle);
        while (!feof($handle)) {
            $chunk = fread($handle, 2 * 1024 * 1024);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $haystack = $overlap . $chunk;
            $pos = strpos($haystack, $needle);
            if ($pos !== false) {
                $absolute = ftell($handle) - strlen($chunk) - strlen($overlap) + $pos + $needleLength;
                fseek($handle, $absolute);

                return $handle;
            }

            $overlap = substr($haystack, -$needleLength);
        }

        fclose($handle);

        return null;
    }

    /** @param resource $handle */
    private function skipWhitespace($handle): void
    {
        while (!feof($handle)) {
            $char = fgetc($handle);
            if ($char === false || !ctype_space($char)) {
                if ($char !== false) {
                    fseek($handle, -1, SEEK_CUR);
                }
                break;
            }
        }
    }

    /** @param resource $handle */
    private function readBalanced($handle, string $openChar): string
    {
        $closeChar = $openChar === '[' ? ']' : '}';
        $depth = 1;
        $json = '';
        $inString = false;
        $escape = false;

        while (!feof($handle)) {
            $char = fgetc($handle);
            if ($char === false) {
                break;
            }

            $json .= $char;

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === $openChar) {
                $depth++;
                continue;
            }

            if ($char === $closeChar) {
                $depth--;
                if ($depth === 0) {
                    return $json;
                }
            }
        }

        return $json;
    }
}
