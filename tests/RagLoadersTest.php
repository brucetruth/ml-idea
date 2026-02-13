<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Loaders\PdoLoader;
use PHPUnit\Framework\TestCase;

final class RagLoadersTest extends TestCase
{
    public function testPdoLoaderLoadsRowsAndMetadata(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('sqlite PDO driver is not available.');
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE docs (id TEXT PRIMARY KEY, text TEXT NOT NULL, topic TEXT, source TEXT)');

        $stmt = $pdo->prepare('INSERT INTO docs(id,text,topic,source) VALUES (:id,:text,:topic,:source)');
        $stmt->execute([':id' => 'd1', ':text' => 'save and load models', ':topic' => 'persistence', ':source' => 'db']);
        $stmt->execute([':id' => 'd2', ':text' => 'cross validation workflow', ':topic' => 'evaluation', ':source' => 'db']);

        $loader = new PdoLoader(
            $pdo,
            'SELECT id, text, topic, source FROM docs WHERE topic = :topic',
            textField: 'text',
            idField: 'id',
            params: [':topic' => 'persistence'],
            metadataFields: ['topic', 'source']
        );

        $docs = $loader->load();

        self::assertCount(1, $docs);
        self::assertSame('d1', $docs[0]->id);
        self::assertSame('save and load models', $docs[0]->text);
        self::assertSame('persistence', $docs[0]->metadata['topic']);
    }
}
