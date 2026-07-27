<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\ChallengeFile;
use App\Models\ChallengeTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ChallengeSeeder extends Seeder
{
    private const READABLE_FLAG = 'HTP\{[a-zA-Z0-9_]+\}';

    public function run(): void
    {
        foreach ($this->challenges() as $data) {
            $attrs = $data['attrs'];
            $attrs['points'] = Challenge::DIFFICULTY_POINTS[$attrs['difficulty']];
            $attrs['flag_format'] ??= self::READABLE_FLAG;

            $challenge = Challenge::updateOrCreate(
                ['slug' => $attrs['slug']],
                array_merge($attrs, [
                    'status' => Challenge::STATUS_PUBLISHED,
                    'flag_type' => Challenge::FLAG_STATIC,
                    'published_at' => now(),
                ]),
            );

            foreach ($data['translations'] as $locale => $trans) {
                ChallengeTranslation::updateOrCreate(
                    ['challenge_id' => $challenge->id, 'locale' => $locale],
                    $trans,
                );
            }

            foreach ($data['files'] ?? [] as $file) {
                $this->storeFile($challenge, $file);
            }
        }
    }

    /**
     * @param  array{filename: string, mime_type: string}  $file
     */
    private function storeFile(Challenge $challenge, array $file): void
    {
        $storagePath = sprintf('%s/%s', $challenge->slug, $file['filename']);
        $assetPath = database_path(sprintf(
            'seeders/assets/challenges/%s/%s',
            $challenge->slug,
            $file['filename'],
        ));

        $contents = is_file($assetPath)
            ? (string) file_get_contents($assetPath)
            : "Placeholder asset — real file missing.\n";

        Storage::disk('challenges')->put($storagePath, $contents);

        ChallengeFile::updateOrCreate(
            ['challenge_id' => $challenge->id, 'filename' => $file['filename']],
            [
                'mime_type' => $file['mime_type'],
                'storage_path' => $storagePath,
                'size_bytes' => strlen($contents),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function challenges(): array
    {
        return [
            // ---------- CRYPTO ----------
            [
                'attrs' => ['slug' => 'matryoshka', 'category' => 'crypto', 'difficulty' => 'easy', 'static_flag' => 'HTP{layers_all_the_way_down}'],
                'translations' => [
                    'en' => [
                        'title' => 'Matryoshka',
                        'description' => "Encoding is not encryption — but stack enough layers and it *feels* like a puzzle.\n\n`cipher.txt` holds a flag wrapped in several rounds of common encodings. Peel them one at a time until printable text falls out.",
                        'hint_1' => 'The outermost and innermost layers are Base64. Look at the character set to tell Base64 from Base32.',
                        'hint_2' => 'Order from outside in: Base64 → Base32 → Base64.',
                    ],
                    'ru' => [
                        'title' => 'Матрёшка',
                        'description' => "Кодирование — не шифрование, но если сложить достаточно слоёв, получается головоломка.\n\nВ `cipher.txt` флаг завёрнут в несколько раундов обычных кодировок. Снимайте их по одному, пока не покажется читаемый текст.",
                        'hint_1' => 'Внешний и внутренний слои — Base64. Отличайте Base64 от Base32 по алфавиту.',
                        'hint_2' => 'Порядок снаружи внутрь: Base64 → Base32 → Base64.',
                    ],
                ],
                'files' => [['filename' => 'cipher.txt', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'single-malt', 'category' => 'crypto', 'difficulty' => 'medium', 'static_flag' => 'HTP{x0r_1s_not_encryption}'],
                'translations' => [
                    'en' => [
                        'title' => 'Single Malt',
                        'description' => "One byte of key. That's the whole secret.\n\n`cipher.hex` is the flag XOR-ed against a single repeating byte. There are only 256 possibilities — let the machine try them all.",
                        'hint_1' => 'Decode the hex to raw bytes first, then XOR every byte against a candidate key 0x00–0xFF.',
                        'hint_2' => 'The correct key produces printable text that starts with `HTP{` and ends with `}`.',
                    ],
                    'ru' => [
                        'title' => 'Односолодовый',
                        'description' => "Один байт ключа — вот и весь секрет.\n\n`cipher.hex` — это флаг, XOR-нутый с одним повторяющимся байтом. Всего 256 вариантов — пусть машина переберёт их.",
                        'hint_1' => 'Сначала переведите hex в байты, затем XOR каждого байта с кандидатом 0x00–0xFF.',
                        'hint_2' => 'Верный ключ даёт читаемый текст, начинающийся с `HTP{` и заканчивающийся `}`.',
                    ],
                ],
                'files' => [['filename' => 'cipher.hex', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'weak-rsa', 'category' => 'crypto', 'difficulty' => 'medium', 'static_flag' => 'HTP{sh0r_would_be_proud}'],
                'translations' => [
                    'en' => [
                        'title' => 'Weak RSA',
                        'description' => "Textbook RSA with a fatal parameter choice: the two primes are very close together.\n\n`key.txt` gives you `n`, `e` and the ciphertext `c`. Factor `n`, rebuild the private key, and decrypt.",
                        'hint_1' => 'When p and q are close, √n sits just below p. Fermat factorization finds them in a handful of steps.',
                        'hint_2' => 'Once you have p and q: d = e⁻¹ mod (p-1)(q-1), m = c^d mod n, then convert the integer back to bytes.',
                    ],
                    'ru' => [
                        'title' => 'Слабый RSA',
                        'description' => "Учебный RSA с фатальным выбором параметров: два простых числа очень близки.\n\nВ `key.txt` даны `n`, `e` и шифртекст `c`. Разложите `n`, восстановите приватный ключ и расшифруйте.",
                        'hint_1' => 'Когда p и q близки, √n чуть меньше p. Факторизация Ферма находит их за несколько шагов.',
                        'hint_2' => 'Имея p и q: d = e⁻¹ mod (p-1)(q-1), m = c^d mod n, затем число обратно в байты.',
                    ],
                ],
                'files' => [['filename' => 'key.txt', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'repeat-offender', 'category' => 'crypto', 'difficulty' => 'hard', 'static_flag' => 'HTP{fr3quency_analysis_wins}'],
                'translations' => [
                    'en' => [
                        'title' => 'Repeat Offender',
                        'description' => "A block of English prose was encrypted with repeating-key XOR. The flag is embedded in the plaintext.\n\n`cipher.bin` is raw bytes. Recover the key length, then the key, then read the message.",
                        'hint_1' => 'Find the key length with Hamming-distance / Kasiski analysis. It is short.',
                        'hint_2' => 'Split the ciphertext into columns by key length; each column is a single-byte XOR you can solve with letter-frequency scoring.',
                    ],
                    'ru' => [
                        'title' => 'Рецидивист',
                        'description' => "Блок английского текста зашифрован XOR с повторяющимся ключом. Флаг вшит в открытый текст.\n\n`cipher.bin` — сырые байты. Найдите длину ключа, затем ключ, затем прочтите сообщение.",
                        'hint_1' => 'Определите длину ключа через расстояние Хэмминга / метод Касиски. Ключ короткий.',
                        'hint_2' => 'Разбейте шифртекст на столбцы по длине ключа; каждый столбец — однобайтовый XOR, решаемый частотным анализом.',
                    ],
                ],
                'files' => [['filename' => 'cipher.bin', 'mime_type' => 'application/octet-stream']],
            ],

            // ---------- FORENSICS ----------
            [
                'attrs' => ['slug' => 'needle', 'category' => 'forensics', 'difficulty' => 'easy', 'static_flag' => 'HTP{gr3p_is_your_friend}'],
                'translations' => [
                    'en' => [
                        'title' => 'Needle',
                        'description' => "Four thousand lines of log noise. One of them isn't noise.\n\nDownload `dump.log` and find the flag hiding among the garbage.",
                        'hint_1' => 'You do not need to read it by hand. The flag matches a very specific pattern.',
                        'hint_2' => "grep -o 'HTP{[^}]*}' dump.log",
                    ],
                    'ru' => [
                        'title' => 'Иголка',
                        'description' => "Четыре тысячи строк лог-шума. Одна из них — не шум.\n\nСкачайте `dump.log` и найдите флаг среди мусора.",
                        'hint_1' => 'Читать вручную не нужно. Флаг соответствует очень конкретному шаблону.',
                        'hint_2' => "grep -o 'HTP{[^}]*}' dump.log",
                    ],
                ],
                'files' => [['filename' => 'dump.log', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'snapshot', 'category' => 'forensics', 'difficulty' => 'easy', 'static_flag' => 'HTP{m3tadata_never_lies}'],
                'translations' => [
                    'en' => [
                        'title' => 'Snapshot',
                        'description' => "A perfectly boring photo. The picture is not the point.\n\nInspect the file's metadata in `snapshot.jpg` — photographers leave notes.",
                        'hint_1' => 'EXIF stores more than camera settings. Look at the UserComment / Artist fields.',
                        'hint_2' => 'exiftool snapshot.jpg  — or just  strings snapshot.jpg | grep HTP',
                    ],
                    'ru' => [
                        'title' => 'Снимок',
                        'description' => "Совершенно скучное фото. Дело не в картинке.\n\nИзучите метаданные файла `snapshot.jpg` — фотографы оставляют заметки.",
                        'hint_1' => 'EXIF хранит не только настройки камеры. Смотрите поля UserComment / Artist.',
                        'hint_2' => 'exiftool snapshot.jpg — или просто strings snapshot.jpg | grep HTP',
                    ],
                ],
                'files' => [['filename' => 'snapshot.jpg', 'mime_type' => 'image/jpeg']],
            ],
            [
                'attrs' => ['slug' => 'polyglot', 'category' => 'forensics', 'difficulty' => 'medium', 'static_flag' => 'HTP{h1dden_in_plain_bytes}'],
                'translations' => [
                    'en' => [
                        'title' => 'Polyglot',
                        'description' => "It opens as an image. It is also something else.\n\n`artwork.png` renders fine, but the file is bigger than the picture needs. Something is appended after the image data.",
                        'hint_1' => 'A ZIP central directory is read from the END of a file, so an archive can live behind a valid image.',
                        'hint_2' => 'unzip artwork.png  — or run binwalk to carve the trailing archive.',
                    ],
                    'ru' => [
                        'title' => 'Полиглот',
                        'description' => "Открывается как картинка. И не только.\n\n`artwork.png` рисуется нормально, но файл больше, чем нужно изображению. После данных картинки что-то дописано.",
                        'hint_1' => 'ZIP читает оглавление с КОНЦА файла, поэтому архив может прятаться за валидной картинкой.',
                        'hint_2' => 'unzip artwork.png — или binwalk для извлечения хвостового архива.',
                    ],
                ],
                'files' => [['filename' => 'artwork.png', 'mime_type' => 'image/png']],
            ],
            [
                'attrs' => ['slug' => 'deep-freeze', 'category' => 'forensics', 'difficulty' => 'hard', 'static_flag' => 'HTP{russian_dolls_of_zlib}'],
                'translations' => [
                    'en' => [
                        'title' => 'Deep Freeze',
                        'description' => "Compression, compression, compression.\n\n`payload.bin` was gzipped more than once. Keep decompressing until you reach text.",
                        'hint_1' => 'The magic bytes 1F 8B mark a gzip member. If the output still starts with 1F 8B, you are not done.',
                        'hint_2' => 'cat payload.bin | gunzip | gunzip | gunzip',
                    ],
                    'ru' => [
                        'title' => 'Глубокая заморозка',
                        'description' => "Сжатие, сжатие, сжатие.\n\n`payload.bin` был сжат gzip несколько раз. Распаковывайте, пока не дойдёте до текста.",
                        'hint_1' => 'Байты 1F 8B — признак gzip. Если вывод снова начинается с 1F 8B — вы не закончили.',
                        'hint_2' => 'cat payload.bin | gunzip | gunzip | gunzip',
                    ],
                ],
                'files' => [['filename' => 'payload.bin', 'mime_type' => 'application/gzip']],
            ],

            // ---------- WEB ----------
            [
                'attrs' => ['slug' => 'crumbs', 'category' => 'web', 'difficulty' => 'easy', 'static_flag' => 'HTP{c00kies_are_not_secure}'],
                'translations' => [
                    'en' => [
                        'title' => 'Crumbs',
                        'description' => "The app stores your session state client-side and trusts it blindly.\n\n`request.txt` is a captured HTTP request. Decode the `session` cookie and read what the server put in it.",
                        'hint_1' => 'The cookie value is Base64URL. It decodes to JSON.',
                        'hint_2' => 'Base64URL uses - and _ instead of + and /, and the padding may be stripped.',
                    ],
                    'ru' => [
                        'title' => 'Крошки',
                        'description' => "Приложение хранит состояние сессии на клиенте и слепо ему доверяет.\n\n`request.txt` — перехваченный HTTP-запрос. Декодируйте куку `session` и прочтите, что туда положил сервер.",
                        'hint_1' => 'Значение куки — Base64URL. Декодируется в JSON.',
                        'hint_2' => 'Base64URL использует - и _ вместо + и /, а паддинг может быть срезан.',
                    ],
                ],
                'files' => [['filename' => 'request.txt', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'weak-jwt', 'category' => 'web', 'difficulty' => 'medium', 'static_flag' => 'HTP{weak_hs256_secret}'],
                'translations' => [
                    'en' => [
                        'title' => 'Weak Signature',
                        'description' => "This admin token is signed with HS256 — symmetric, so the signing secret *is* the password.\n\n`token.txt` holds a JWT. `wordlist.txt` holds candidate secrets. Recover the secret; wrap it as `HTP{<secret>}`.",
                        'hint_1' => 'For each candidate, recompute HMAC-SHA256 over `header.payload` and compare the Base64URL signature.',
                        'hint_2' => 'The winning secret is the flag body. jwt_tool or a five-line script both work.',
                    ],
                    'ru' => [
                        'title' => 'Слабая подпись',
                        'description' => "Этот админ-токен подписан HS256 — симметрично, значит секрет подписи и есть пароль.\n\nВ `token.txt` — JWT. В `wordlist.txt` — кандидаты секретов. Найдите секрет; оберните как `HTP{<secret>}`.",
                        'hint_1' => 'Для каждого кандидата пересчитайте HMAC-SHA256 по `header.payload` и сравните Base64URL-подпись.',
                        'hint_2' => 'Верный секрет — тело флага. Подойдёт jwt_tool или скрипт в пять строк.',
                    ],
                ],
                'files' => [
                    ['filename' => 'token.txt', 'mime_type' => 'text/plain'],
                    ['filename' => 'wordlist.txt', 'mime_type' => 'text/plain'],
                ],
            ],
            [
                'attrs' => ['slug' => 'source-sleuth', 'category' => 'web', 'difficulty' => 'medium', 'static_flag' => 'HTP{cl1ent_side_checks_lol}'],
                'translations' => [
                    'en' => [
                        'title' => 'Source Sleuth',
                        'description' => "Never trust the client. This login validates the password entirely in JavaScript.\n\n`login.js` reconstructs the correct password at runtime. Read the code and compute it yourself — that string is the flag.",
                        'hint_1' => 'The check reverses an array and subtracts a constant from each value, then compares against your input.',
                        'hint_2' => 'You do not even need to reverse it by hand — paste the logic into a console and print the target string instead of comparing.',
                    ],
                    'ru' => [
                        'title' => 'Сыщик исходников',
                        'description' => "Никогда не доверяй клиенту. Этот вход проверяет пароль целиком в JavaScript.\n\n`login.js` собирает верный пароль во время выполнения. Прочтите код и вычислите его сами — эта строка и есть флаг.",
                        'hint_1' => 'Проверка разворачивает массив и вычитает константу из каждого значения, затем сравнивает с вводом.',
                        'hint_2' => 'Разворачивать вручную не нужно — вставьте логику в консоль и выведите целевую строку вместо сравнения.',
                    ],
                ],
                'files' => [['filename' => 'login.js', 'mime_type' => 'text/javascript']],
            ],
            [
                'attrs' => ['slug' => 'forged-in-fire', 'category' => 'web', 'difficulty' => 'hard', 'static_flag' => 'HTP{type_juggl1ng_php}'],
                'translations' => [
                    'en' => [
                        'title' => 'Forged in Fire',
                        'description' => "A classic PHP footgun: loose comparison of hashes.\n\n`admin.php` compares `md5(\$_GET['token'])` to a stored hash using `==`. Find an input that bypasses the check. When you understand *why* it works, submit `HTP{type_juggl1ng_php}`.",
                        'hint_1' => 'The stored hash looks like `0e` followed by only digits. PHP treats `0e<digits>` strings as the float 0 in a `==` comparison.',
                        'hint_2' => 'You need an input whose md5 is also `0e<all-digits>`. The string `240610708` is the famous one.',
                    ],
                    'ru' => [
                        'title' => 'Выковано в огне',
                        'description' => "Классическая ловушка PHP: нестрогое сравнение хэшей.\n\n`admin.php` сравнивает `md5(\$_GET['token'])` с сохранённым хэшем через `==`. Найдите вход, обходящий проверку. Поняв, *почему* это работает, сдайте `HTP{type_juggl1ng_php}`.",
                        'hint_1' => 'Сохранённый хэш выглядит как `0e` и дальше только цифры. PHP трактует строки `0e<цифры>` как float 0 при сравнении `==`.',
                        'hint_2' => 'Нужен вход, чей md5 тоже `0e<только-цифры>`. Знаменитая строка — `240610708`.',
                    ],
                ],
                'files' => [['filename' => 'admin.php', 'mime_type' => 'text/x-php']],
            ],

            // ---------- LLM ----------
            [
                'attrs' => ['slug' => 'whisper', 'category' => 'llm', 'difficulty' => 'easy', 'static_flag' => 'HTP{pr0mpt_says_no_but_base64}'],
                'translations' => [
                    'en' => [
                        'title' => 'Whisper',
                        'description' => "A leaked system prompt tells the assistant to *never* reveal its internal key — but the author thought encoding the key would keep it safe.\n\nRead `system_prompt.txt` and recover the key.",
                        'hint_1' => 'The key is stored right there in the prompt, just encoded.',
                        'hint_2' => 'That blob is Base64. Decode it.',
                    ],
                    'ru' => [
                        'title' => 'Шёпот',
                        'description' => "Утёкший системный промпт велит ассистенту *никогда* не раскрывать внутренний ключ — но автор решил, что кодирование ключа его защитит.\n\nПрочтите `system_prompt.txt` и восстановите ключ.",
                        'hint_1' => 'Ключ лежит прямо в промпте, просто закодирован.',
                        'hint_2' => 'Этот блок — Base64. Декодируйте.',
                    ],
                ],
                'files' => [['filename' => 'system_prompt.txt', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'context-bleed', 'category' => 'llm', 'difficulty' => 'medium', 'static_flag' => 'HTP{r3trieval_augmented_leak}'],
                'translations' => [
                    'en' => [
                        'title' => 'Context Bleed',
                        'description' => "A RAG assistant refuses to print the internal token in one piece — but it happily quoted fragments of it while summarizing three different documents.\n\n`chat.log` is the transcript. Reassemble the token.",
                        'hint_1' => 'Each assistant turn leaks one chunk of the token between ellipses.',
                        'hint_2' => 'Concatenate the three leaked chunks in order and wrap them as HTP{...}.',
                    ],
                    'ru' => [
                        'title' => 'Протечка контекста',
                        'description' => "RAG-ассистент отказывается печатать внутренний токен целиком — но охотно процитировал его фрагменты, пересказывая три разных документа.\n\n`chat.log` — стенограмма. Соберите токен заново.",
                        'hint_1' => 'Каждый ответ ассистента протекает одним куском токена между многоточиями.',
                        'hint_2' => 'Соедините три куска по порядку и оберните как HTP{...}.',
                    ],
                ],
                'files' => [['filename' => 'chat.log', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'tokenizer-trap', 'category' => 'llm', 'difficulty' => 'hard', 'static_flag' => 'HTP{z3ro_width_smuggling}'],
                'translations' => [
                    'en' => [
                        'title' => 'Tokenizer Trap',
                        'description' => "The assistant swore it sent a plain, harmless reply. Your terminal agrees. Your hex editor does not.\n\n`transcript.txt` smuggles data inside invisible characters. Extract it.",
                        'hint_1' => 'Between the visible words are zero-width characters (U+200B / U+200C). Two symbols → binary.',
                        'hint_2' => 'Map ZWSP→0 and ZWNJ→1, group into bytes, decode as ASCII.',
                    ],
                    'ru' => [
                        'title' => 'Ловушка токенизатора',
                        'description' => "Ассистент клялся, что прислал безобидный ответ. Терминал согласен. Hex-редактор — нет.\n\n`transcript.txt` прячет данные в невидимых символах. Извлеките их.",
                        'hint_1' => 'Между видимыми словами — символы нулевой ширины (U+200B / U+200C). Два символа → двоичный код.',
                        'hint_2' => 'ZWSP→0, ZWNJ→1, соберите в байты, декодируйте как ASCII.',
                    ],
                ],
                'files' => [['filename' => 'transcript.txt', 'mime_type' => 'text/plain']],
            ],

            // ---------- OSINT ----------
            [
                'attrs' => ['slug' => 'geotag', 'category' => 'osint', 'difficulty' => 'easy', 'static_flag' => 'HTP{registan_samarkand}'],
                'translations' => [
                    'en' => [
                        'title' => 'Geotag',
                        'description' => "Where was this taken?\n\n`photo.jpg` carries GPS coordinates in its metadata. Read them, drop the pin on a map, and identify the famous public landmark.\n\nFlag: `HTP{<landmark>_<city>}` — lowercase, underscores. (The landmark is a world-famous public square.)",
                        'hint_1' => 'Pull the GPSLatitude / GPSLongitude EXIF tags and convert them to decimal degrees.',
                        'hint_2' => 'Those coordinates land on a UNESCO square in Uzbekistan.',
                    ],
                    'ru' => [
                        'title' => 'Геометка',
                        'description' => "Где это снято?\n\n`photo.jpg` содержит GPS-координаты в метаданных. Прочтите их, поставьте точку на карте и опознайте знаменитый публичный памятник.\n\nФлаг: `HTP{<памятник>_<город>}` — строчными, через подчёркивание. (Это всемирно известная площадь.)",
                        'hint_1' => 'Возьмите EXIF-теги GPSLatitude / GPSLongitude и переведите в десятичные градусы.',
                        'hint_2' => 'Эти координаты указывают на площадь ЮНЕСКО в Узбекистане.',
                    ],
                ],
                'files' => [['filename' => 'photo.jpg', 'mime_type' => 'image/jpeg']],
            ],
            [
                'attrs' => ['slug' => 'handle-hunt', 'category' => 'osint', 'difficulty' => 'medium', 'static_flag' => 'HTP{osint_is_just_reading}'],
                'translations' => [
                    'en' => [
                        'title' => 'Handle Hunt',
                        'description' => "The flag was split into three pieces and scattered across an exported profile bundle. No external sites needed — everything is in the files.\n\nCollect all three `tag` values from the three files and join them with underscores inside `HTP{...}`.",
                        'hint_1' => 'One tag hides in an HTML comment (Base64), one in the readme (ROT13), one is the filename of the .dat file.',
                        'hint_2' => 'Order: HTML comment first, readme second, filename third.',
                    ],
                    'ru' => [
                        'title' => 'Охота за ником',
                        'description' => "Флаг разбит на три части и разбросан по выгруженному профилю. Внешние сайты не нужны — всё в файлах.\n\nСоберите три значения `tag` из трёх файлов и соедините подчёркиваниями внутри `HTP{...}`.",
                        'hint_1' => 'Один тег в HTML-комментарии (Base64), один в readme (ROT13), один — имя .dat-файла.',
                        'hint_2' => 'Порядок: сначала HTML-комментарий, затем readme, затем имя файла.',
                    ],
                ],
                'files' => [
                    ['filename' => 'profile.html', 'mime_type' => 'text/html'],
                    ['filename' => 'readme.txt', 'mime_type' => 'text/plain'],
                    ['filename' => 'tag3_just_reading.dat', 'mime_type' => 'application/octet-stream'],
                ],
            ],

            // ---------- MISC ----------
            [
                'attrs' => ['slug' => 'qr-quest', 'category' => 'misc', 'difficulty' => 'easy', 'static_flag' => 'HTP{scan_me_maybe}'],
                'translations' => [
                    'en' => [
                        'title' => 'QR Quest',
                        'description' => "No trick here — just point something at it.\n\n`code.png` is a QR code. Scan it and read the flag.",
                        'hint_1' => 'A phone camera works. So does an offline decoder like zbarimg.',
                        'hint_2' => 'zbarimg code.png',
                    ],
                    'ru' => [
                        'title' => 'QR-квест',
                        'description' => "Без подвоха — просто наведите что-нибудь на него.\n\n`code.png` — это QR-код. Отсканируйте и прочтите флаг.",
                        'hint_1' => 'Подойдёт камера телефона. Или офлайн-декодер вроде zbarimg.',
                        'hint_2' => 'zbarimg code.png',
                    ],
                ],
                'files' => [['filename' => 'code.png', 'mime_type' => 'image/png']],
            ],
            [
                'attrs' => ['slug' => 'brainrot', 'category' => 'misc', 'difficulty' => 'medium', 'static_flag' => 'HTP{turing_tarpit_escape}'],
                'translations' => [
                    'en' => [
                        'title' => 'Brainrot',
                        'description' => "An esoteric language with eight instructions and zero mercy.\n\n`program.bf` is a Brainfuck program. Run it — its output is the flag.",
                        'hint_1' => 'Brainfuck uses only > < + - . , [ ]. Any online or five-line interpreter will do.',
                        'hint_2' => 'The program only writes output (no input needed); just capture stdout.',
                    ],
                    'ru' => [
                        'title' => 'Мозгогниль',
                        'description' => "Эзотерический язык из восьми инструкций и без пощады.\n\n`program.bf` — программа на Brainfuck. Запустите её — вывод и есть флаг.",
                        'hint_1' => 'Brainfuck использует только > < + - . , [ ]. Подойдёт любой интерпретатор.',
                        'hint_2' => 'Программа только пишет вывод (ввод не нужен); просто снимите stdout.',
                    ],
                ],
                'files' => [['filename' => 'program.bf', 'mime_type' => 'text/plain']],
            ],
            [
                'attrs' => ['slug' => 'esoterica', 'category' => 'misc', 'difficulty' => 'hard', 'static_flag' => 'HTP{decode_decompile_deflate}'],
                'translations' => [
                    'en' => [
                        'title' => 'Esoterica',
                        'description' => "A three-stage transform stands between you and the flag.\n\n`stage1.txt` was produced by: flag → zlib deflate → Base64 → reverse the string. Undo it in the opposite order.",
                        'hint_1' => 'First reverse the string. The result is standard Base64.',
                        'hint_2' => 'Base64-decode, then zlib/inflate the bytes to get the flag.',
                    ],
                    'ru' => [
                        'title' => 'Эзотерика',
                        'description' => "Между вами и флагом — трёхэтапное преобразование.\n\n`stage1.txt` получен так: флаг → zlib deflate → Base64 → разворот строки. Отмените в обратном порядке.",
                        'hint_1' => 'Сначала разверните строку. Результат — обычный Base64.',
                        'hint_2' => 'Декодируйте Base64, затем zlib/inflate байтов — получите флаг.',
                    ],
                ],
                'files' => [['filename' => 'stage1.txt', 'mime_type' => 'text/plain']],
            ],
        ];
    }
}
