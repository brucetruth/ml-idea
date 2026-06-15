<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Detect;

/** ISO 639-1 language catalog for detection, routing, and NLP pipelines. */
final class LanguageRegistry
{
    /** @var array<string, array{code:string, name:string, native:string, script:string, family:string, aliases:array<int,string>, pos:string}>|null */
    private static ?array $catalog = null;

    /** @var array<string, array<string, float>>|null */
    private static ?array $profiles = null;

    /** @return array<string, array{code:string, name:string, native:string, script:string, family:string, aliases:array<int,string>, pos:string}> */
    public static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        self::$catalog = self::buildCatalog();

        return self::$catalog;
    }

    /** @return array<string, array<string, float>> */
    public static function profiles(): array
    {
        if (self::$profiles !== null) {
            return self::$profiles;
        }

        /** @var array<string, array<string, float>> $profiles */
        $profiles = require __DIR__ . '/data/language_profiles.php';
        self::$profiles = $profiles;

        return self::$profiles;
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::profiles());
    }

    public static function count(): int
    {
        return count(self::profiles());
    }

    public static function has(string $code): bool
    {
        return isset(self::profiles()[self::resolve($code)]);
    }

    /** @return array<string, array<int, string>> code => family => codes */
    public static function byFamily(): array
    {
        $groups = [];
        foreach (self::catalog() as $code => $meta) {
            if (!self::has($code)) {
                continue;
            }
            $groups[$meta['family']][] = $code;
        }
        foreach ($groups as $family => $codes) {
            sort($groups[$family]);
        }
        ksort($groups);

        return $groups;
    }

    /** @return array<string, array<int, string>> script => codes */
    public static function byScript(): array
    {
        $groups = [];
        foreach (self::catalog() as $code => $meta) {
            if (!self::has($code)) {
                continue;
            }
            $groups[$meta['script']][] = $code;
        }
        foreach ($groups as $script => $codes) {
            sort($groups[$script]);
        }
        ksort($groups);

        return $groups;
    }

    /** @return array{code:string, name:string, native:string, script:string, family:string, aliases:array<int,string>, pos:string}|null */
    public static function get(string $code): ?array
    {
        $code = self::resolve($code);

        return self::catalog()[$code] ?? null;
    }

    public static function resolve(string $input): string
    {
        $input = mb_strtolower(trim($input));
        if ($input === '') {
            return 'unknown';
        }

        if (isset(self::catalog()[$input])) {
            return $input;
        }

        foreach (self::catalog() as $code => $meta) {
            if (in_array($input, $meta['aliases'], true)) {
                return $code;
            }
        }

        return $input;
    }

    /** POS tagger profile: languages with dedicated heuristics, else English rules. */
    public static function posTaggerLanguage(string $code): string
    {
        $code = self::resolve($code);
        $meta = self::catalog()[$code] ?? null;
        if ($meta === null) {
            return 'en';
        }

        $pos = $meta['pos'];

        return in_array($pos, ['en', 'fr', 'es', 'de', 'pt', 'it', 'nl'], true) ? $pos : 'en';
    }

    /** @return array<int, string> */
    public static function listNames(): array
    {
        $out = [];
        foreach (self::catalog() as $code => $meta) {
            $out[$code] = $meta['name'];
        }

        ksort($out);

        return $out;
    }

    /**
     * @param array{code:string, name:string, native:string, script:string, family:string, aliases:array<int,string>, pos:string} $row
     */
    private static function row(
        string $code,
        string $name,
        string $native,
        string $script,
        string $family,
        array $aliases = [],
        string $pos = 'en',
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'native' => $native,
            'script' => $script,
            'family' => $family,
            'aliases' => $aliases,
            'pos' => $pos,
        ];
    }

    /** @return array<string, array{code:string, name:string, native:string, script:string, family:string, aliases:array<int,string>, pos:string}> */
    private static function buildCatalog(): array
    {
        return [
            'en' => self::row('en', 'English', 'English', 'Latin', 'germanic', ['eng', 'english'], 'en'),
            'fr' => self::row('fr', 'French', 'Français', 'Latin', 'romance', ['fra', 'fre', 'french'], 'fr'),
            'es' => self::row('es', 'Spanish', 'Español', 'Latin', 'romance', ['spa', 'spanish'], 'es'),
            'de' => self::row('de', 'German', 'Deutsch', 'Latin', 'germanic', ['deu', 'ger', 'german'], 'de'),
            'pt' => self::row('pt', 'Portuguese', 'Português', 'Latin', 'romance', ['por', 'portuguese'], 'pt'),
            'it' => self::row('it', 'Italian', 'Italiano', 'Latin', 'romance', ['ita', 'italian'], 'it'),
            'nl' => self::row('nl', 'Dutch', 'Nederlands', 'Latin', 'germanic', ['nld', 'dut', 'dutch'], 'nl'),
            'pl' => self::row('pl', 'Polish', 'Polski', 'Latin', 'slavic', ['pol', 'polish']),
            'sv' => self::row('sv', 'Swedish', 'Svenska', 'Latin', 'germanic', ['swe', 'swedish']),
            'da' => self::row('da', 'Danish', 'Dansk', 'Latin', 'germanic', ['dan', 'danish']),
            'no' => self::row('no', 'Norwegian', 'Norsk', 'Latin', 'germanic', ['nor', 'norwegian', 'nb', 'nob']),
            'fi' => self::row('fi', 'Finnish', 'Suomi', 'Latin', 'uralic', ['fin', 'finnish']),
            'cs' => self::row('cs', 'Czech', 'Čeština', 'Latin', 'slavic', ['ces', 'cze', 'czech']),
            'sk' => self::row('sk', 'Slovak', 'Slovenčina', 'Latin', 'slavic', ['slk', 'slo', 'slovak']),
            'hu' => self::row('hu', 'Hungarian', 'Magyar', 'Latin', 'uralic', ['hun', 'hungarian']),
            'ro' => self::row('ro', 'Romanian', 'Română', 'Latin', 'romance', ['ron', 'rum', 'romanian']),
            'hr' => self::row('hr', 'Croatian', 'Hrvatski', 'Latin', 'slavic', ['hrv', 'croatian']),
            'sl' => self::row('sl', 'Slovenian', 'Slovenščina', 'Latin', 'slavic', ['slv', 'slovenian']),
            'et' => self::row('et', 'Estonian', 'Eesti', 'Latin', 'uralic', ['est', 'estonian']),
            'lv' => self::row('lv', 'Latvian', 'Latviešu', 'Latin', 'baltic', ['lav', 'latvian']),
            'lt' => self::row('lt', 'Lithuanian', 'Lietuvių', 'Latin', 'baltic', ['lit', 'lithuanian']),
            'ga' => self::row('ga', 'Irish', 'Gaeilge', 'Latin', 'celtic', ['gle', 'irish']),
            'ca' => self::row('ca', 'Catalan', 'Català', 'Latin', 'romance', ['cat', 'catalan']),
            'eu' => self::row('eu', 'Basque', 'Euskara', 'Latin', 'basque', ['eus', 'baq', 'basque']),
            'gl' => self::row('gl', 'Galician', 'Galego', 'Latin', 'romance', ['glg', 'galician']),
            'is' => self::row('is', 'Icelandic', 'Íslenska', 'Latin', 'germanic', ['isl', 'ice', 'icelandic']),
            'mt' => self::row('mt', 'Maltese', 'Malti', 'Latin', 'semitic', ['mlt', 'maltese']),
            'sq' => self::row('sq', 'Albanian', 'Shqip', 'Latin', 'albanian', ['sqi', 'alb', 'albanian']),
            'bs' => self::row('bs', 'Bosnian', 'Bosanski', 'Latin', 'slavic', ['bos', 'bosnian']),
            'mk' => self::row('mk', 'Macedonian', 'Македонски', 'Cyrillic', 'slavic', ['mkd', 'mac', 'macedonian']),
            'sr' => self::row('sr', 'Serbian', 'Srpski', 'Latin', 'slavic', ['srp', 'serbian']),
            'af' => self::row('af', 'Afrikaans', 'Afrikaans', 'Latin', 'germanic', ['afr', 'afrikaans']),
            'cy' => self::row('cy', 'Welsh', 'Cymraeg', 'Latin', 'celtic', ['cym', 'wel', 'welsh']),
            'lb' => self::row('lb', 'Luxembourgish', 'Lëtzebuergesch', 'Latin', 'germanic', ['ltz', 'luxembourgish']),
            'be' => self::row('be', 'Belarusian', 'Беларуская', 'Cyrillic', 'slavic', ['bel', 'belarusian']),
            'ar' => self::row('ar', 'Arabic', 'العربية', 'Arabic', 'semitic', ['ara', 'arabic']),
            'he' => self::row('he', 'Hebrew', 'עברית', 'Hebrew', 'semitic', ['heb', 'hebrew']),
            'fa' => self::row('fa', 'Persian', 'فارسی', 'Arabic', 'iranian', ['fas', 'per', 'persian', 'farsi']),
            'ur' => self::row('ur', 'Urdu', 'اردو', 'Arabic', 'indic', ['urd', 'urdu']),
            'tr' => self::row('tr', 'Turkish', 'Türkçe', 'Latin', 'turkic', ['tur', 'turkish']),
            'az' => self::row('az', 'Azerbaijani', 'Azərbaycan', 'Latin', 'turkic', ['aze', 'azerbaijani']),
            'uz' => self::row('uz', 'Uzbek', 'Oʻzbek', 'Latin', 'turkic', ['uzb', 'uzbek']),
            'kk' => self::row('kk', 'Kazakh', 'Қазақ', 'Cyrillic', 'turkic', ['kaz', 'kazakh']),
            'ru' => self::row('ru', 'Russian', 'Русский', 'Cyrillic', 'slavic', ['rus', 'russian']),
            'uk' => self::row('uk', 'Ukrainian', 'Українська', 'Cyrillic', 'slavic', ['ukr', 'ukrainian']),
            'bg' => self::row('bg', 'Bulgarian', 'Български', 'Cyrillic', 'slavic', ['bul', 'bulgarian']),
            'el' => self::row('el', 'Greek', 'Ελληνικά', 'Greek', 'hellenic', ['ell', 'gre', 'greek']),
            'hi' => self::row('hi', 'Hindi', 'हिन्दी', 'Devanagari', 'indic', ['hin', 'hindi']),
            'bn' => self::row('bn', 'Bengali', 'বাংলা', 'Bengali', 'indic', ['ben', 'bengali', 'bangla']),
            'ta' => self::row('ta', 'Tamil', 'தமிழ்', 'Tamil', 'dravidian', ['tam', 'tamil']),
            'te' => self::row('te', 'Telugu', 'తెలుగు', 'Telugu', 'dravidian', ['tel', 'telugu']),
            'mr' => self::row('mr', 'Marathi', 'मराठी', 'Devanagari', 'indic', ['mar', 'marathi']),
            'ne' => self::row('ne', 'Nepali', 'नेपाली', 'Devanagari', 'indic', ['nep', 'nepali']),
            'pa' => self::row('pa', 'Punjabi', 'ਪੰਜਾਬੀ', 'Gurmukhi', 'indic', ['pan', 'punjabi']),
            'gu' => self::row('gu', 'Gujarati', 'ગુજરાતી', 'Gujarati', 'indic', ['guj', 'gujarati']),
            'kn' => self::row('kn', 'Kannada', 'ಕನ್ನಡ', 'Kannada', 'dravidian', ['kan', 'kannada']),
            'ml' => self::row('ml', 'Malayalam', 'മലയാളം', 'Malayalam', 'dravidian', ['mal', 'malayalam']),
            'si' => self::row('si', 'Sinhala', 'සිංහල', 'Sinhala', 'indic', ['sin', 'sinhala', 'sinhalese']),
            'vi' => self::row('vi', 'Vietnamese', 'Tiếng Việt', 'Latin', 'austroasiatic', ['vie', 'vietnamese']),
            'th' => self::row('th', 'Thai', 'ไทย', 'Thai', 'tai', ['tha', 'thai']),
            'id' => self::row('id', 'Indonesian', 'Bahasa Indonesia', 'Latin', 'malayo-polynesian', ['ind', 'indonesian']),
            'ms' => self::row('ms', 'Malay', 'Bahasa Melayu', 'Latin', 'malayo-polynesian', ['msa', 'may', 'malay']),
            'tl' => self::row('tl', 'Tagalog', 'Tagalog', 'Latin', 'malayo-polynesian', ['tgl', 'tagalog', 'fil', 'filipino']),
            'my' => self::row('my', 'Burmese', 'မြန်မာ', 'Myanmar', 'sino-tibetan', ['mya', 'bur', 'burmese']),
            'km' => self::row('km', 'Khmer', 'ខ្មែរ', 'Khmer', 'austroasiatic', ['khm', 'khmer', 'cambodian']),
            'lo' => self::row('lo', 'Lao', 'ລາວ', 'Lao', 'tai', ['lao', 'laotian']),
            'ja' => self::row('ja', 'Japanese', '日本語', 'Japanese', 'japonic', ['jpn', 'japanese']),
            'ko' => self::row('ko', 'Korean', '한국어', 'Hangul', 'koreanic', ['kor', 'korean']),
            'zh' => self::row('zh', 'Chinese', '中文', 'Han', 'sino-tibetan', ['zho', 'chi', 'chinese', 'cmn', 'mandarin']),
            'sw' => self::row('sw', 'Swahili', 'Kiswahili', 'Latin', 'bantu', ['swa', 'swahili', 'kiswahili']),
            'ha' => self::row('ha', 'Hausa', 'Hausa', 'Latin', 'afro-asiatic', ['hau', 'hausa']),
            'yo' => self::row('yo', 'Yoruba', 'Yorùbá', 'Latin', 'niger-congo', ['yor', 'yoruba']),
            'ig' => self::row('ig', 'Igbo', 'Igbo', 'Latin', 'niger-congo', ['ibo', 'igbo']),
            'zu' => self::row('zu', 'Zulu', 'isiZulu', 'Latin', 'bantu', ['zul', 'zulu', 'isizulu']),
            'xh' => self::row('xh', 'Xhosa', 'isiXhosa', 'Latin', 'bantu', ['xho', 'xhosa', 'isixhosa']),
            'sn' => self::row('sn', 'Shona', 'chiShona', 'Latin', 'bantu', ['sna', 'shona', 'chishona']),
            'am' => self::row('am', 'Amharic', 'አማርኛ', 'Ethiopic', 'semitic', ['amh', 'amharic']),
            'mg' => self::row('mg', 'Malagasy', 'Malagasy', 'Latin', 'austronesian', ['mlg', 'malagasy']),
            'bem' => self::row('bem', 'Bemba', 'Ichibemba', 'Latin', 'bantu', ['chibemba']),
            'nya' => self::row('nya', 'Nyanja', 'Chichewa', 'Latin', 'bantu', ['ny', 'nyanja', 'chichewa', 'chewa']),
            'toi' => self::row('toi', 'Tonga', 'Chitonga', 'Latin', 'bantu', ['to', 'tonga', 'chitonga']),
            'loz' => self::row('loz', 'Lozi', 'Silozi', 'Latin', 'bantu', ['lozi', 'silozi']),
            'ps' => self::row('ps', 'Pashto', 'پښتو', 'Arabic', 'iranian', ['pus', 'pashto']),
            'ku' => self::row('ku', 'Kurdish', 'Kurdî', 'Latin', 'iranian', ['kur', 'kurdish']),
            'hy' => self::row('hy', 'Armenian', 'Հայերեն', 'Armenian', 'armenian', ['hye', 'arm', 'armenian']),
            'ka' => self::row('ka', 'Georgian', 'ქართული', 'Georgian', 'kartvelian', ['kat', 'geo', 'georgian']),
            'mn' => self::row('mn', 'Mongolian', 'Монгол', 'Cyrillic', 'mongolic', ['mon', 'mongolian']),
            'bo' => self::row('bo', 'Tibetan', 'བོད་ཡིག', 'Tibetan', 'sino-tibetan', ['bod', 'tib', 'tibetan']),
            'jv' => self::row('jv', 'Javanese', 'Basa Jawa', 'Latin', 'malayo-polynesian', ['jav', 'javanese']),
            'su' => self::row('su', 'Sundanese', 'Basa Sunda', 'Latin', 'malayo-polynesian', ['sun', 'sundanese']),
            'ht' => self::row('ht', 'Haitian Creole', 'Kreyòl ayisyen', 'Latin', 'creole', ['hat', 'haitian']),
            'rw' => self::row('rw', 'Kinyarwanda', 'Ikinyarwanda', 'Latin', 'bantu', ['kin', 'kinyarwanda']),
            'so' => self::row('so', 'Somali', 'Soomaali', 'Latin', 'afro-asiatic', ['som', 'somali']),
            'ti' => self::row('ti', 'Tigrinya', 'ትግርኛ', 'Ethiopic', 'semitic', ['tir', 'tigrinya']),
            'om' => self::row('om', 'Oromo', 'Afaan Oromoo', 'Latin', 'afro-asiatic', ['orm', 'oromo']),
            'st' => self::row('st', 'Sesotho', 'Sesotho', 'Latin', 'bantu', ['sot', 'sesotho', 'sotho']),
            'tn' => self::row('tn', 'Setswana', 'Setswana', 'Latin', 'bantu', ['tsn', 'tswana', 'setswana']),
            'gd' => self::row('gd', 'Scottish Gaelic', 'Gàidhlig', 'Latin', 'celtic', ['gla', 'gd', 'scottish gaelic']),
            'ky' => self::row('ky', 'Kyrgyz', 'Кыргызча', 'Cyrillic', 'turkic', ['kir', 'kyrgyz']),
            'tg' => self::row('tg', 'Tajik', 'Тоҷикӣ', 'Cyrillic', 'iranian', ['tgk', 'tajik']),
            'ug' => self::row('ug', 'Uyghur', 'ئۇيغۇرچە', 'Arabic', 'turkic', ['uig', 'uyghur', 'uighur']),
            'sd' => self::row('sd', 'Sindhi', 'سنڌي', 'Arabic', 'indic', ['snd', 'sindhi']),
            'as' => self::row('as', 'Assamese', 'অসমীয়া', 'Bengali', 'indic', ['asm', 'assamese']),
            'or' => self::row('or', 'Odia', 'ଓଡ଼ିଆ', 'Oriya', 'indic', ['ori', 'odia', 'oriya']),
        ];
    }
}
