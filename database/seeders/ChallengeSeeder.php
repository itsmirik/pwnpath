<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\ChallengeFile;
use App\Models\ChallengeTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $challenges = [
            [
                'attrs' => [
                    'slug' => 'cookie-monster',
                    'category' => 'web',
                    'difficulty' => 'easy',
                    'points' => Challenge::DIFFICULTY_POINTS['easy'],
                    'flag_format' => 'HTP\{[a-f0-9]{24}\}',
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Cookie Monster',
                        'description' => "A junior developer built their first admin panel and left a debug endpoint online.\n\nInspect the response and the cookies the app sets on your browser. Something tasty is hiding in plain sight.\n\n```http\nGET /admin/debug HTTP/1.1\nHost: cookie-monster.htp.local\n```\n\nGoal: recover the admin's session token and submit the flag baked into it.",
                        'hint_1' => 'Open DevTools → Application → Cookies. What is unusual about the values?',
                        'hint_2' => 'The debug endpoint reflects unescaped user input into the response body.',
                    ],
                    'ru' => [
                        'title' => 'Cookie Monster',
                        'description' => "Джуниор-разработчик собрал свою первую админку и забыл выключить отладочный эндпоинт.\n\nПосмотрите на ответ и куки, которые сайт ставит в браузер. Что-то вкусное прячется на виду.\n\n```http\nGET /admin/debug HTTP/1.1\nHost: cookie-monster.htp.local\n```\n\nЗадача: получить сессионный токен админа и сдать вшитый в него флаг.",
                        'hint_1' => 'Откройте DevTools → Application → Cookies. Что в значениях необычного?',
                        'hint_2' => 'Отладочный эндпоинт возвращает пользовательский ввод без экранирования.',
                    ],
                ],
                'files' => [
                    ['filename' => 'source.zip', 'size_bytes' => 24_576, 'mime_type' => 'application/zip'],
                ],
            ],
            [
                'attrs' => [
                    'slug' => 'caesars-ghost',
                    'category' => 'crypto',
                    'difficulty' => 'easy',
                    'points' => Challenge::DIFFICULTY_POINTS['easy'],
                    'flag_format' => 'HTP\{[a-f0-9]{24}\}',
                ],
                'translations' => [
                    'en' => [
                        'title' => "Caesar's Ghost",
                        'description' => "Julius left a note before losing the imperial keys.\n\nThe letters look Roman but read like riot. Perhaps a shift will settle them.\n\n```\nKWS{q31c5r7...}\n```\n\nDecode the ciphertext in `ghost.txt` and submit the plaintext flag.",
                        'hint_1' => 'The message uses a classical rotation cipher — try all 25 shifts.',
                        'hint_2' => 'Only lowercase hexadecimal characters should remain inside the braces.',
                    ],
                    'ru' => [
                        'title' => 'Дух Цезаря',
                        'description' => "Юлий оставил записку перед тем, как потерял ключи от империи.\n\nБуквы выглядят по-римски, а читаются как хаос. Возможно, помогут сдвиги.\n\n```\nKWS{q31c5r7...}\n```\n\nРасшифруйте `ghost.txt` и сдайте открытый флаг.",
                        'hint_1' => 'Здесь классический сдвиговый шифр — попробуйте все 25 вариантов.',
                        'hint_2' => 'Внутри фигурных скобок должны остаться только строчные hex-символы.',
                    ],
                ],
                'files' => [
                    ['filename' => 'ghost.txt', 'size_bytes' => 512, 'mime_type' => 'text/plain'],
                ],
            ],
            [
                'attrs' => [
                    'slug' => 'context-leak',
                    'category' => 'llm',
                    'difficulty' => 'medium',
                    'points' => Challenge::DIFFICULTY_POINTS['medium'],
                    'flag_format' => 'HTP\{[a-f0-9]{24}\}',
                ],
                'translations' => [
                    'en' => [
                        'title' => 'Context Leak',
                        'description' => "A retail chatbot loads product docs into its context and refuses to reveal the internal spec sheet.\n\nA colleague uploaded a suspiciously-crafted PDF into the vendor catalog and now the bot occasionally leaks internal notes.\n\nYour job: coax the assistant into printing the hidden section labelled `# HTP-INTERNAL` and submit the flag it contains.",
                        'hint_1' => 'The system prompt trusts every document in the retrieval index equally.',
                        'hint_2' => 'Craft a query that references the internal document as if it were part of the user manual.',
                    ],
                    'ru' => [
                        'title' => 'Утечка контекста',
                        'description' => "Ритейл-бот подгружает документы товаров в контекст и отказывается показывать внутреннюю спеку.\n\nКоллега залил в каталог PDF с интересной начинкой, и теперь бот иногда протекает.\n\nЗадача: вытяните из ассистента скрытую секцию `# HTP-INTERNAL` и сдайте вшитый в неё флаг.",
                        'hint_1' => 'Системный промпт одинаково доверяет всем документам в индексе.',
                        'hint_2' => 'Сформулируйте запрос так, будто внутренний документ — часть пользовательской инструкции.',
                    ],
                ],
                'files' => [
                    ['filename' => 'catalog.pdf', 'size_bytes' => 128_000, 'mime_type' => 'application/pdf'],
                    ['filename' => 'chat.log', 'size_bytes' => 4_096, 'mime_type' => 'text/plain'],
                ],
            ],
        ];

        foreach ($challenges as $data) {
            $challenge = Challenge::updateOrCreate(
                ['slug' => $data['attrs']['slug']],
                array_merge($data['attrs'], [
                    'status' => Challenge::STATUS_PUBLISHED,
                    'flag_type' => Challenge::FLAG_DYNAMIC,
                    'published_at' => now(),
                ]),
            );

            foreach ($data['translations'] as $locale => $trans) {
                ChallengeTranslation::updateOrCreate(
                    ['challenge_id' => $challenge->id, 'locale' => $locale],
                    $trans,
                );
            }

            foreach ($data['files'] as $file) {
                $storagePath = sprintf('%s/%s', $challenge->slug, $file['filename']);
                $placeholder = sprintf(
                    "Placeholder content for %s.\nReal challenge assets are not authored yet — see plan §16.\n",
                    $file['filename'],
                );

                Storage::disk('challenges')->put($storagePath, $placeholder);

                ChallengeFile::updateOrCreate(
                    ['challenge_id' => $challenge->id, 'filename' => $file['filename']],
                    [
                        'mime_type' => $file['mime_type'],
                        'storage_path' => $storagePath,
                        'size_bytes' => strlen($placeholder),
                    ],
                );
            }
        }
    }
}
