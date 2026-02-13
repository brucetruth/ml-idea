<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Loaders;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\DocumentLoaderInterface;
use ML\IDEA\RAG\Document;

final class DirectoryLoader implements DocumentLoaderInterface
{
    /**
     * @param array<int, string> $extensions
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $extensions = ['txt', 'md'],
    ) {
        if (!is_dir($this->directory)) {
            throw new InvalidArgumentException(sprintf('Directory not found: %s', $this->directory));
        }
    }

    public function load(): array
    {
        $docs = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory));

        foreach ($it as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $this->extensions, true)) {
                continue;
            }

            $path = $file->getPathname();
            $text = file_get_contents($path);
            if ($text === false || trim($text) === '') {
                continue;
            }

            $docs[] = new Document(
                'file:' . md5($path),
                $text,
                ['source' => 'file', 'path' => $path, 'extension' => $ext]
            );
        }

        return $docs;
    }
}
