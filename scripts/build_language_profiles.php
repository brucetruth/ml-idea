<?php

declare(strict_types=1);

/**
 * Build trigram profiles from distinctive seed text per ISO 639-1 code.
 * Run: php scripts/build_language_profiles.php > src/NLP/Detect/data/language_profiles.php
 */

function trigrams(string $text): array
{
    $s = mb_strtolower($text);
    $s = (string) preg_replace('/[^\p{L}\s]+/u', ' ', $s);
    $s = trim((string) preg_replace('/\s+/u', ' ', $s));
    if ($s === '') {
        return [];
    }

    $grams = [];
    $chars = preg_split('//u', ' ' . $s . ' ', -1, PREG_SPLIT_NO_EMPTY) ?: [];
    for ($i = 0; $i < count($chars) - 2; $i++) {
        $g = $chars[$i] . $chars[$i + 1] . $chars[$i + 2];
        $grams[$g] = ($grams[$g] ?? 0) + 1;
    }

    arsort($grams);
    $top = array_slice($grams, 0, 18, true);
    $max = max($top) ?: 1;
    $out = [];
    foreach ($top as $g => $c) {
        $out[$g] = round(0.03 + 0.07 * ($c / $max), 3);
    }

    return $out;
}

/** @return array<string, string> */
function seeds(): array
{
    $repeat = static fn (string $text, int $n = 4): string => implode(' ', array_fill(0, $n, $text));

    return [
        'en' => $repeat('The quick brown fox jumps over the lazy dog while the government considers international policy.'),
        'fr' => $repeat('Bonjour le monde français avec des accents éèêë où nous parlons souvent le matin à Paris.'),
        'es' => $repeat('El español tiene ñ y verbos especiales porque hablamos con claridad en Madrid y México.'),
        'de' => $repeat('Der schnelle braune Fuchs springt über den faulen Hund in der Bundesrepublik Deutschland.'),
        'pt' => $repeat('O português tem ção e palavras brasileiras porque falamos com calma em Lisboa e São Paulo.'),
        'it' => $repeat('L italiano usa molte preposizioni semplici nel parlare quotidiano a Roma e Milano.'),
        'nl' => $repeat('De Nederlandse taal heeft ij digrammen vaak en woorden zoals het water en de brug.'),
        'pl' => $repeat('Szybki brązowy lis przeskakuje przez leniwego psa w języku polskim w Warszawie.'),
        'sv' => $repeat('Den snabba bruna räven hoppar över den lata hunden på svenska i Stockholm.'),
        'da' => $repeat('Den hurtige brune ræv hopper over den dovne hund på dansk i København.'),
        'no' => $repeat('Den raske brune reven hopper over den late hunden på norsk i Oslo.'),
        'fi' => $repeat('Nopea ruskea kettu hyppää laiskan koiran yli suomen kielellä Helsingissä.'),
        'cs' => $repeat('Rychlá hnědá liška skáče přes líného psa v českém jazyce v Praze.'),
        'sk' => $repeat('Rýchla hnedá líška skáče cez lenivého psa v slovenskom jazyku v Bratislave.'),
        'hu' => $repeat('A gyors barna róka átugrik a lusta kutya felett magyar nyelven Budapesten.'),
        'ro' => $repeat('Vulpea maro rapidă sare peste câinele leneș în limba română în București.'),
        'hr' => $repeat('Brza smeđa lisica preskače lijenog psa na hrvatskom jeziku u Zagrebu.'),
        'sl' => $repeat('Hitra rjava lisica preskoči lenega psa v slovenskem jeziku v Ljubljani.'),
        'et' => $repeat('Kiire pruun rebane hüppab üle laisa koera eesti keeles Tallinnas.'),
        'lv' => $repeat('Ātra brūnā lapsa lec pāri slinkajam sunim latviešu valodā Rīgā.'),
        'lt' => $repeat('Greita ruda lapė peršoka per tingų šunį lietuvių kalba Vilniuje.'),
        'ga' => $repeat('Tá an sionnach donn tapa ag léim thar an madra leisciúil as Gaeilge i mBaile Átha Cliath.'),
        'ca' => $repeat('El català és parlat a Barcelona amb paraules pròpies com ara bon dia i adéu.'),
        'eu' => $repeat('Euskara Euskal Herrian hitz egiten da eta hizkuntza berezia da Donostian.'),
        'gl' => $repeat('O galego fala en Galicia con palabras propias como bo día e adeus en Santiago.'),
        'is' => $repeat('Hinn hraði brúni refur hoppar yfir letihundinn á íslensku í Reykjavík.'),
        'mt' => $repeat('Il Malti huwa lingwa Semitika mitkellem f Malta bil kliem tieghu stess.'),
        'sq' => $repeat('Shqipja flitet në Tiranë me fjalë të veçanta dhe shqiptarë kudo.'),
        'bs' => $repeat('Brza smeđa lisica preskače lijenog psa na bosanskom jeziku u Sarajevu.'),
        'mk' => $repeat('Брзата кафена лисица прескокнува мрзеливото куче на македонски јазик во Скопје.'),
        'sr' => $repeat('Brza smeđa lisica preskače lenjog psa na srpskom jeziku u Beogradu.'),
        'af' => $repeat('Die vinnige bruin jakkals spring oor die lui hond in Afrikaans in Kaapstad.'),
        'cy' => $repeat('Mae rhaid i ni siarad Cymraeg yn y gymuned yma yng Nghymru bob dydd.'),
        'lb' => $repeat('Den lëtzebuergesche Sproochraum huet eegene Wierder zu Lëtzebuerg.'),
        'be' => $repeat('Хуткая рыжая лісіца пераскочыць лянівага сабаку на беларускай мове ў Мінску.'),
        'ar' => $repeat('الثعلب البني السريع يقفز فوق الكلب الكسول في اللغة العربية في القاهرة.'),
        'he' => $repeat('השועל החום המהיר קופץ מעל הכלב העצלן בעברית בירושלים.'),
        'fa' => $repeat('روباه قهوه‌ای سریع از روی سگ تنبل به زبان فارسی در تهران می‌پرد.'),
        'ur' => $repeat('تیز بھوری لومڑی سست کتے کے اوپر سے اردو زبان میں اسلام آباد میں چھلانگ لگati ہے.'),
        'tr' => $repeat('Hızlı kahverengi tilki tembel köpeğin üzerinden Türkçe dilinde İstanbulda atlar.'),
        'az' => $repeat('Sürətli qırmızı tülkü tənbəl itin üstündən Azərbaycanca Bakıda tullanır.'),
        'uz' => $repeat('Tez jigarrang tulki dangasa it ustidan ozbek tilida Toshkentda sakraydi.'),
        'kk' => $repeat('Жылдам қоңыр түлкі жалқау иттің үстінен қазақ тілінде Астанада секіреді.'),
        'ru' => $repeat('Быстрая коричневая лиса перепрыгивает через ленивую собаку на русском языке в Москве.'),
        'uk' => $repeat('Швидка коричнева лисиця перестрибує лінивого пса українською мовою в Києві.'),
        'bg' => $repeat('Бързата кафена лисица прескача мързеливото куче на български език в София.'),
        'el' => $repeat('Η γρήγορη καφέ αλεπού πηδάει πάνω από το τεμπέλικο σκυλί στα ελληνικά στην Αθήνα.'),
        'hi' => $repeat('तेज़ भूरी लोमड़ी आलसी कुत्ते के उपर से हिन्दी भाषा में दिल्ली में कूदती है।'),
        'bn' => $repeat('দ্রুত বাদামী শিয়াল অলস কুকুরের উপর দিয়ে বাংলা ভাষায় ঢাকায় লাফ দেয়।'),
        'ta' => $repeat('வேகமான பழுப்பு நரி சோம்பேறி நாயை தமிழ் மொழியில் சென்னையில் கடந்து குதிக்கிறது.'),
        'te' => $repeat('వేగంగా బ్రౌన్ ఫాక্স సోమరి కుక్కను తెలుగు భాషలో హైదరాబాద్లో దాటి jumping.'),
        'vi' => $repeat('Con cáo nâu nhanh nhảy qua con chó lười bằng tiếng Việt tại Hà Nội.'),
        'mr' => $repeat('जलद तपकिरी कोल्हा आळशी कुत्र्यावरून मराठी भाषेत मुंबईत उडी मारतो.'),
        'ne' => $repeat('छिटो खैरो fox ललित कुकुर माथि नेपाली भाषामा काठमाडौंमा कुद्छ.'),
        'pa' => $repeat('ਤੇਜ਼ ਭੂਰਾ ਲੂੰਬੜੀ ਸੁਸਤ ਕੁੱਤੇ ਤੋਂ ਪੰਜਾਬੀ ਭਾਸ਼ਾ ਵਿੱਚ ਲਾਹੌਰ ਵਿੱਚ ਛalang.'),
        'gu' => $repeat('ઝડપી ભૂરો શિયાળ ઉદાસ કૂતરા પર ગુજરાતી ભાષામાં અમદાવાદમાં કૂદે છે.'),
        'kn' => $repeat('ವೇಗವಾದ ಕಂದು ನರಿ ಸೋಮಾರಿ ನಾಯಿಯ ಮೇಲೆ ಕನ್ನಡ ಭಾಷೆಯಲ್ಲಿ ಬೆಂಗಳೂರಿನಲ್ಲಿ ಹಾರುತ್ತದೆ.'),
        'ml' => $repeat('വേഗത്തിലുള്ള തവിട്ട് നരി മടിയനായ നായയുടെ മുകളിലൂടെ മലയാളം ഭാഷയിൽ കൊച്ചിയിൽ ചാടുന്നു.'),
        'si' => $repeat('වේගවත් දුඹුරු fox කම්මැලි dog එක Sinhala භාෂාවෙන් Colombo.'),
        'th' => $repeat('สุนัขขี้เกียจกระโดดผ่านสุนัขขี้เกียจเป็นภาษาไทยในกรุงเทพฯ'),
        'id' => $repeat('Rubah cokelat cepat melompati anjing malas dalam bahasa Indonesia di Jakarta.'),
        'ms' => $repeat('Rubah coklat pantas melompati anjing malas dalam bahasa Melayu di Kuala Lumpur.'),
        'tl' => $repeat('Ang mabilis na kayumangging soro ay tumalon sa tamad na aso sa Tagalog sa Maynila.'),
        'my' => $repeat('လျင်မြန်သော အညိုရောင်မြေခွေးသည် Myanmar ဘာသာစကားဖြင့် Yangon တွင်'),
        'km' => $repeat('កញ្ជ្រោងត្នោតលឿនល hops over the lazy dog in Khmer in Phnom Penh.'),
        'lo' => $repeat('ໂກງສີນ້ຳຕານແລ່ນໄວກະໂດຂ້າມຫມາຂີ້ຄ້ານເປັນພາສາລາວໃນວຽງຈັນ.'),
        'ja' => $repeat('速い茶色の狐は怠惰な犬を飛び越えます。日本語で東京では毎日話します。'),
        'ko' => $repeat('빠른 갈색 여우가 게으른 개를 뛰어넘습니다. 한국어로 서울에서 매일 말합니다.'),
        'zh' => $repeat('快速的棕色狐狸跳过了懒狗。中文在北京和上海每天使用。'),
        'sw' => $repeat('Mbweha wa kahawia wa haraka huruka juu ya mbwa mvivu kwa Kiswahili Dar es Salaam.'),
        'ha' => $repeat('Zaki mai saurin gudu mai launin kasa ya tsallake karnuka a Hausa a Kano.'),
        'yo' => $repeat('Kekere alawọ ekun ti yara fo lori ajá lálà Yorùbá ní Lagos.'),
        'ig' => $repeat('Nwa azụ ojii ngwa ngwa na-egbapụ nkita umengwụ n asụsụ Igbo na Lagos.'),
        'zu' => $repeat('Impungushe ebrown esheshayo yeqa inja evila ngesiZulu eThekwini.'),
        'xh' => $repeat('Impungushe ebrown esheshayo yeqa inja evila ngesiXhosa eMonti.'),
        'sn' => $repeat('Mbira inokurumidza yobrown inotsiva imbwa inonyanya kushusha muchiShona muHarare.'),
        'am' => $repeat('ፈጣን ቡrown fox በአማርኛ በአዲስ አበባ ላይ ላዳቢ dog jumps.'),
        'mg' => $repeat('Ny sakafo volomparasy malemy mandalo ny alika malaina amin ny teny Malagasy eto Antananarivo.'),
        'bem' => $repeat('Ulubee lwa mushishi ulwenda pa imbwa ya bulumende mu Chibemba mu Lusaka.'),
        'nya' => $repeat('Nkhandwe ya chikasima imadumpha pa galu waulesi mu Chichewa mu Lilongwe.'),
        'toi' => $repeat('Ngoma ya bunono yagunda pa mbwa ya bulumi mu Chitonga mu Livingstone.'),
        'loz' => $repeat('Muholo wa mubundu u fela fa ntja ya buta ka Silozi kwa Mongu.'),
        'ps' => $repeat('چټک قهوې رنګ روبان د سست سپي څخه په پښتو ژبه کې په کابل کې ځپي.'),
        'ku' => $repeat('Rêvebirî zêrîn a lez di zimanê kurdî de li Hewlêrê di ser kûçikê tembel re diçe.'),
        'hy' => $repeat('Արագ brown fox-ը կամացած շան վրայից ցատկում է հայerenով Երևանում.'),
        'ka' => $repeat('სწრაფი ყავისფერი მელა ზარმაცი ძაღლის გადახტება ქართულად თბილისში.'),
        'mn' => $repeat('Хурдан бор үнэг залхуу нохойн дээгүүр Монгол хэлээр Улаанбаатарт үсэрдэг.'),
        'bo' => $repeat('མགྱོགས་པོའི་སྨུག་པའི་ཝ་ཕག་ལེ་ལོག་གི་ཁྱིའི་སྟེང་ནས་བོད་ཡིག་གིས་ལྷ་སར་མཆོངས.'),
        'jv' => $repeat('Rubah coklat cepet mlumpat saka asu males basa Jawa ing Yogyakarta.'),
        'su' => $repeat('Reuwase coklat gancang ngaleupaskeun anjing males dina basa Sunda di Bandung.'),
        'ht' => $repeat('Renn vulp li kouri sou chen an pares nan lang Kreyòl ayisyen nan Pòtoprens.'),
        'rw' => $repeat('Impungushe yihuta yica imbwa ivuna mu Kinyarwanda i Kigali.'),
        'so' => $repeat('Ri waalan ee maroon ah wuxuu ka boodaa eyga caajiska ah af Soomaali Muqdisho.'),
        'ti' => $repeat('ቅልጡፍ ቡrown fox ኣብ ትግርኛ ኣብ ኣስመራ ኣብ ላዕሊ ድሑር ከልኤ.'),
        'om' => $repeat('Shanxi ariitiin magaalaan saree dadhabboo irra darbuu afaan Oromoo Finfinnee.'),
        'st' => $repeat('Phokho e lebelo e tshweu e tlola ntja ea lesele ka Sesotho Maseru.'),
        'tn' => $repeat('Phokwe e lephephe e tshweu e tlola ntja e botlhale ka Setswana Gaborone.'),
        'gd' => $repeat('Tha an sionnach donn luath a leum thar an chon leisg ann an Gàidhlig ann an Dùn Èideann.'),
        'ky' => $repeat('Тез күрөң түлкү жалкоо иттин ustunon kyrgyz tilinde Bishkekte sakrat.'),
        'tg' => $repeat('Робоҳи зардии зуд аз рӯи sagi танбал ба забони тоҷикӣ дар Dushanbe.'),
        'ug' => $repeat('تېز قەھۋە رەڭلىك تۈلۈك ھورۇن ئىtten ئۇيغۇرچە ئۈرۈمچیدە sakraydu.'),
        'sd' => $repeat('تيز ڀuri لومڙي سست ڪتي تي سنڌي ٻولي ۾ ڪراچي ۾ کودي ٿي.'),
        'as' => $repeat('গতিশীল বাদামী শিয়াল অলস কুকura ওপৰত অসমীয়া ভাষাত গুৱাহাটীত.'),
        'or' => $repeat('ଦ୍ରୁତ ବାଦାମୀ ଶିଆଳ ଆଳସ୍ୟ କୁକୁର ଉପରେ ଓଡ଼ିଆ ଭାଷାରେ ଭୁବନେଶ୍ୱରରେ ଡେଇଁଥାଏ.'),
    ];
}

$profiles = [];
foreach (seeds() as $code => $seed) {
    $profiles[$code] = trigrams($seed);
}

echo "<?php\n\ndeclare(strict_types=1);\n\n/** Auto-generated trigram profiles for language detection. */\nreturn " . var_export($profiles, true) . ";\n";
