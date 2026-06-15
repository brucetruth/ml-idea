<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Language;
use ML\IDEA\NLP\Nlp;
use ML\IDEA\NLP\Text\Text;
use PHPUnit\Framework\TestCase;

final class SpacyStyleNlpApiTest extends TestCase
{
    public function testNlpLoadReturnsLanguageThatBuildsDoc(): void
    {
        $nlp = Nlp::load('en_core');
        $doc = $nlp->process('Alice visited Paris on Monday.');

        self::assertSame('en', $doc->language);
        self::assertNotEmpty($doc->tokens);
        self::assertNotEmpty($doc->sents);
        self::assertNotEmpty($doc->languageSegments);
        self::assertSame('en_core', $nlp->modelName());
    }

    public function testLanguagePipeProcessesBatch(): void
    {
        $docs = Nlp::blank('en')->pipe(['Hello world.', 'Good morning.']);
        self::assertCount(2, $docs);
        self::assertSame(1, $docs[0]->summary()['sents']);
    }

    public function testTextToDocUsesDetectedLanguagePipeline(): void
    {
        $doc = Text::of('Alice visited Paris.')->toDoc();
        self::assertInstanceOf(\ML\IDEA\NLP\Doc\Doc::class, $doc);
        self::assertNotEmpty($doc->tokens);
    }

    public function testPipelineRegistryListsNamedModels(): void
    {
        $models = Nlp::models();
        self::assertArrayHasKey('en_core', $models);
        self::assertArrayHasKey('zambia-bem', $models);
    }

    public function testZambiaModelLoadsBembaPipeline(): void
    {
        $nlp = Nlp::load('zambia-bem');
        self::assertSame('bem', $nlp->languageCode());
        self::assertInstanceOf(Language::class, $nlp);
    }

    public function testAddPipeAndDisablePipes(): void
    {
        $nlp = Nlp::blank('en');
        self::assertTrue($nlp->hasPipe('tagger'));
        self::assertContains('ner', $nlp->pipeNames());

        $lite = $nlp->disablePipes(['ner', 'tagger']);
        $doc = $lite->process('Hello world.');
        self::assertNotEmpty($doc->tokens);
        self::assertEmpty($doc->ents);
        self::assertNull($doc->tokens[0]->pos);
    }

    public function testDocSpansAndCallableBackend(): void
    {
        $doc = Nlp::load('en_core')->process('Alice visited Paris.');
        self::assertNotEmpty($doc->spans());
        foreach ($doc->spans('PROPER_NOUN') as $span) {
            self::assertArrayHasKey('start', $span);
            self::assertArrayHasKey('end', $span);
        }

        $enriched = Nlp::blank('en')->withBackend(new \ML\IDEA\NLP\Backends\CallableNlpBackend(
            static fn (string $text, \ML\IDEA\NLP\Doc\Doc $draft): \ML\IDEA\NLP\Doc\Doc => new \ML\IDEA\NLP\Doc\Doc(
                text: $draft->text,
                tokens: $draft->tokens,
                ents: $draft->ents,
                sents: $draft->sents,
                language: $draft->language,
                attrs: array_merge($draft->attrs, ['backend' => 'callable']),
            ),
        ))->process('Test backend hook.');
        self::assertSame('callable', $enriched->attrs['backend']);
    }

    public function testRegionalCoreModelsLoad(): void
    {
        self::assertSame('ja', Nlp::load('ja_core')->languageCode());
        self::assertSame('ar', Nlp::load('ar_core')->languageCode());
        self::assertArrayHasKey('de_core', Nlp::models());
    }
}
