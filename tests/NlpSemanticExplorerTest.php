<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Lexicon\EnglishDictionaryLexicon;
use ML\IDEA\NLP\Lexicon\SemanticExplorer;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Text\Text;
use PHPUnit\Framework\TestCase;

final class NlpSemanticExplorerTest extends TestCase
{
    public function testEnglishDictionarySupportsReverseMeaningLookup(): void
    {
        $csv = $this->writeCsv([
            ['word', 'definition'],
            ['happy', 'feeling or showing pleasure'],
            ['glad', 'feeling pleasure and joy'],
            ['sad', 'feeling sorrow or unhappiness'],
        ]);

        $dict = new EnglishDictionaryLexicon($csv);
        self::assertSame('feeling or showing pleasure', $dict->definition('happy'));

        $matches = $dict->wordsFromMeaning('pleasure joy', 5);
        self::assertContains('happy', $matches);
        self::assertContains('glad', $matches);
    }

    public function testSemanticExplorerWordAndMeaningDirections(): void
    {
        $wn = $this->writeJson([
            'words' => ['happy' => ['happy.a.01']],
            'synsets' => [
                'happy.a.01' => [
                    'definition' => 'feeling or showing pleasure',
                    'synonyms' => ['happy', 'joyful', 'glad'],
                ],
            ],
        ]);
        $csv = $this->writeCsv([
            ['word', 'definition'],
            ['happy', 'feeling or showing pleasure'],
            ['joyful', 'full of joy and delight'],
            ['glad', 'showing pleasure or joy'],
        ]);

        $explorer = new SemanticExplorer(
            new WordNetLexicon($wn),
            new EnglishDictionaryLexicon($csv)
        );

        $insights = $explorer->wordInsights('happy');
        self::assertSame('happy', $insights['word']);
        self::assertContains('joyful', $insights['synonyms']);

        $semantic = $explorer->semanticSearch('showing pleasure joy', 10, 3);
        self::assertNotEmpty($semantic['matches']);
        self::assertNotEmpty($semantic['expanded']);
    }

    public function testTextSemanticsCanUseInjectedExplorer(): void
    {
        $wn = $this->writeJson([
            'words' => ['run' => ['run.v.01']],
            'synsets' => [
                'run.v.01' => [
                    'definition' => 'move swiftly on foot',
                    'synonyms' => ['run', 'sprint'],
                ],
            ],
        ]);
        $csv = $this->writeCsv([
            ['word', 'definition'],
            ['run', 'move swiftly on foot'],
            ['sprint', 'run at full speed'],
        ]);

        $explorer = new SemanticExplorer(new WordNetLexicon($wn), new EnglishDictionaryLexicon($csv));
        $sem = Text::of('run')->semantics($explorer);

        self::assertSame('run', $sem['word']);
        self::assertContains('sprint', $sem['synonyms']);
    }

    private function writeJson(array $data): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mlidea_wn_');
        if ($file === false) {
            self::fail('Could not create temp JSON file');
        }
        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
        return $file;
    }

    /** @param array<int, array<int, string>> $rows */
    private function writeCsv(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mlidea_csv_');
        if ($file === false) {
            self::fail('Could not create temp CSV file');
        }

        $h = fopen($file, 'wb');
        if ($h === false) {
            self::fail('Could not open temp CSV file');
        }

        foreach ($rows as $row) {
            fputcsv($h, $row);
        }
        fclose($h);

        return $file;
    }
}
