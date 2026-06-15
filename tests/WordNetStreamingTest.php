<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Loaders\JsonFileKeyScanner;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use PHPUnit\Framework\TestCase;

final class WordNetStreamingTest extends TestCase
{
    public function testJsonFileKeyScannerReadsNestedValues(): void
    {
        $path = $this->writeJson([
            'words' => ['persist' => ['persist.v.01'], 'dog' => ['dog.n.01']],
            'synsets' => [
                'persist.v.01' => ['definition' => 'continue', 'synonyms' => ['persist', 'remain']],
                'dog.n.01' => ['definition' => 'canine', 'synonyms' => ['dog', 'canine']],
            ],
        ]);

        $scanner = new JsonFileKeyScanner($path);
        self::assertSame(['persist.v.01'], $scanner->readStringArray('persist'));
        self::assertEqualsCanonicalizing(
            ['persist', 'remain'],
            $scanner->readObject('persist.v.01')['synonyms'] ?? [],
        );
    }

    public function testWordNetLexiconStreamsLargeFileWithoutFullDecode(): void
    {
        $fullPath = dirname(__DIR__) . '/src/Dataset/wordnet/wn.json';
        if (!is_file($fullPath) || filesize($fullPath) < 1_048_576) {
            self::markTestSkipped('Full WordNet export not available for streaming test.');
        }

        $before = memory_get_usage(true);
        $lexicon = new WordNetLexicon($fullPath);
        $synonyms = $lexicon->synonyms('happy', 5);
        $after = memory_get_usage(true);

        self::assertNotEmpty($synonyms);
        self::assertLessThan(80 * 1024 * 1024, $after - $before);
    }

    private function writeJson(array $data): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mlidea_json_');
        if ($file === false) {
            self::fail('Unable to create temp file.');
        }

        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));

        return $file;
    }
}
