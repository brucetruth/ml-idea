<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Detect\LanguageRegistry;
use ML\IDEA\NLP\Extract\Stopwords;
use ML\IDEA\NLP\Nlp;
use ML\IDEA\NLP\Text\Text;
use PHPUnit\Framework\TestCase;

final class InternationalLanguagesTest extends TestCase
{
    public function testRegistrySupportsFiftyPlusLanguages(): void
    {
        self::assertGreaterThanOrEqual(100, LanguageRegistry::count());
        self::assertGreaterThanOrEqual(100, count(Nlp::languages()));
        self::assertSame(LanguageRegistry::count(), Nlp::languageCount());
    }

    public function testRegistryResolvesCommonAliases(): void
    {
        self::assertSame('en', LanguageRegistry::resolve('eng'));
        self::assertSame('fr', LanguageRegistry::resolve('fra'));
        self::assertSame('zh', LanguageRegistry::resolve('mandarin'));
        self::assertSame('tl', LanguageRegistry::resolve('filipino'));
        self::assertSame('nya', LanguageRegistry::resolve('chichewa'));
    }

    /** @dataProvider detectionSamples */
    public function testDetectsInternationalSamples(string $text, string $expected): void
    {
        $det = (new LanguageDetector())->detectWithScore($text);
        self::assertSame($expected, $det['language'], 'text: ' . $text);
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function detectionSamples(): array
    {
        return [
            'english' => ['The quick brown fox jumps over the lazy dog in the English countryside near London.', 'en'],
            'french' => ['Bonjour le monde français avec des accents éèêë où nous parlons souvent le matin à Paris.', 'fr'],
            'german' => ['Der schnelle braune Fuchs springt über den faulen Hund in der Bundesrepublik Deutschland.', 'de'],
            'spanish' => ['El español tiene ñ y verbos especiales porque hablamos con claridad en Madrid.', 'es'],
            'portuguese' => ['O português tem ção e palavras brasileiras porque falamos com calma em Lisboa.', 'pt'],
            'italian' => ['L italiano usa molte preposizioni semplici nel parlare quotidiano a Roma.', 'it'],
            'dutch' => ['De Nederlandse taal heeft ij digrammen vaak en woorden zoals het water en de brug.', 'nl'],
            'polish' => ['Szybki brązowy lis przeskakuje przez leniwego psa.', 'pl'],
            'swedish' => ['Den snabba bruna räven hoppar över den lata hunden på svenska i Stockholm.', 'sv'],
            'turkish' => ['Hızlı kahverengi tilki tembel köpeğin üzerinden Türkçe dilinde İstanbulda atlar.', 'tr'],
            'indonesian' => ['Rubah cokelat cepat melompati anjing malas dalam bahasa Indonesia di Jakarta.', 'id'],
            'vietnamese' => ['Con cáo nâu nhanh nhảy qua con chó lười bằng tiếng Việt tại Hà Nội.', 'vi'],
            'japanese' => ['速い茶色の狐は怠惰な犬を飛び越える。', 'ja'],
            'korean' => ['빠른 갈색 여우가 게으른 개를 뛰어넘는다.', 'ko'],
            'chinese' => ['快速的棕色狐狸跳过了懒狗。', 'zh'],
            'russian' => ['Быстрая коричневая лиса перепрыгивает через ленивую собаку.', 'ru'],
            'arabic' => ['الثعلب البني السريع يقفز فوق الكلب الكسول.', 'ar'],
            'hindi' => ['तेज़ भूरी लोमड़ी आलसी कुत्ते के उपर से कूदती है।', 'hi'],
            'swahili' => ['Mbweha wa kahawia wa haraka huruka juu ya mbwa mvivu kwa Kiswahili Dar es Salaam.', 'sw'],
        ];
    }

    public function testStopwordsCoverInternationalLanguages(): void
    {
        self::assertGreaterThanOrEqual(100, Stopwords::supportedCount());
        self::assertContains('und', Stopwords::forLanguage('de'));
        self::assertContains('et', Stopwords::forLanguage('fr'));
        self::assertContains('の', Stopwords::forLanguage('ja'));
        self::assertContains('nga', Stopwords::forLanguage('bem'));
    }

    public function testNlpLanguageNamesIncludesMajorLocales(): void
    {
        $names = Nlp::languageNames();
        self::assertArrayHasKey('ja', $names);
        self::assertArrayHasKey('ko', $names);
        self::assertArrayHasKey('ar', $names);
        self::assertArrayHasKey('ne', $names);
        self::assertArrayHasKey('uz', $names);
        self::assertSame('Japanese', $names['ja']);
    }

    public function testLanguagesByFamilyAndScript(): void
    {
        $families = Nlp::languagesByFamily();
        $scripts = Nlp::languagesByScript();
        self::assertArrayHasKey('romance', $families);
        self::assertArrayHasKey('bantu', $families);
        self::assertArrayHasKey('Latin', $scripts);
        self::assertArrayHasKey('Cyrillic', $scripts);
        self::assertGreaterThanOrEqual(50, array_sum(array_map('count', $families)));
    }

    public function testTextLanguageTopAcrossRegions(): void
    {
        $top = Text::of('Der schnelle braune Fuchs springt über den faulen Hund in Deutschland.')->languageTop(3);
        self::assertNotEmpty($top);
        self::assertSame('de', $top[0]['language']);
    }
}
