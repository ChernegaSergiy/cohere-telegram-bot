<?php

require_once 'vendor/autoload.php';
require_once 'CohereClient.php';

class CohereTelegramBot
{
    private $telegram_token;

    private $cohere_client;

    private $telegram;

    private $update_id = 0;

    private $db;

    public function __construct($telegram_token, $cohere_api_key)
    {
        $this->telegram_token = $telegram_token;
        $this->cohere_client = new CohereClient($cohere_api_key);
        $this->telegram = new \TelegramBot\Api\BotApi($telegram_token);
        $this->initDatabase();
    }

    private function initDatabase()
    {
        $this->db = new \SQLite3('user_context.db');
        $this->db->exec('CREATE TABLE IF NOT EXISTS user_context (
            user_id INTEGER PRIMARY KEY,
            messages TEXT,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS terms_acceptance (
            user_id INTEGER PRIMARY KEY,
            accepted BOOLEAN DEFAULT 0,
            accepted_at DATETIME
        )');
    }

    public function run()
    {
        echo "Bot started. Waiting for messages...\n";

        $cleanup_interval = 3600;
        $last_cleanup = time();

        while (true) {
            $this->processUpdates();

            if (time() - $last_cleanup > $cleanup_interval) {
                $this->cleanupOldContexts();
                $last_cleanup = time();
            }

            sleep(2);
        }
    }

    private function processUpdates()
    {
        try {
            $updates = $this->getUpdates();

            foreach ($updates as $update) {
                if (isset($update['callback_query'])) {
                    $this->handleCallbackQuery($update['callback_query']);
                    $this->update_id = $update['update_id'] + 1;

                    continue;
                }

                if (isset($update['message']['text'])) {
                    $chat_id = $update['message']['chat']['id'];
                    $message_text = trim($update['message']['text']);
                    $message_id = $update['message']['message_id'];
                    $language_code = $this->convert_language_code($update['message']['from']['language_code'] ?? 'en');

                    if ('/start' === $message_text) {
                        $this->handleStartCommand($chat_id, $language_code);
                        $this->update_id = $update['update_id'] + 1;

                        continue;
                    }

                    if (! $this->hasAcceptedTerms($chat_id)) {
                        $this->sendTermsAcceptanceMessage($chat_id, $language_code);
                        $this->update_id = $update['update_id'] + 1;

                        continue;
                    }

                    $user_context = $this->getUserContext($chat_id);
                    $user_context[] = ['role' => 'user', 'content' => $message_text];

                    $this->logDebug('Context before API request:', $user_context);

                    if (empty($user_context)) {
                        $this->telegram->sendMessage($chat_id, 'No valid messages available to generate a response.');

                        continue;
                    }

                    $cohere_response = $this->getCohereResponse($user_context, $chat_id);
                    $this->logDebug('API response:', $cohere_response);

                    if (isset($cohere_response['text'])) {
                        $this->telegram->sendMessage($chat_id, $cohere_response['text'], $cohere_response['parse_mode'] ?? null, false, $message_id);
                    } else {
                        $this->telegram->sendMessage($chat_id, 'Sorry, I couldn\'t generate a response at this time.');
                    }
                }

                $this->update_id = $update['update_id'] + 1;
            }
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
            sleep(5);
        }
    }

    private function convert_language_code($ietf_code)
    {
        $language_map = [
            'af' => 'za',
            'am' => 'et',
            'ar' => 'sa',
            'arn' => 'cl',
            'ary' => 'ma',
            'as' => 'in',
            'az' => 'az',
            'ba' => 'ru',
            'be' => 'by',
            'bg' => 'bg',
            'bn' => 'bd',
            'bo' => 'cn',
            'br' => 'fr',
            'bs' => 'ba',
            'ca' => 'es',
            'ckb' => 'iq',
            'co' => 'fr',
            'cs' => 'cz',
            'cy' => 'gb',
            'da' => 'dk',
            'de' => 'de',
            'dsb' => 'de',
            'dv' => 'mv',
            'el' => 'gr',
            'en' => 'gb',
            'es' => 'es',
            'et' => 'ee',
            'eu' => 'es',
            'fa' => 'ir',
            'fi' => 'fi',
            'fil' => 'ph',
            'fo' => 'fo',
            'fr' => 'fr',
            'fy' => 'nl',
            'ga' => 'ie',
            'gd' => 'gb',
            'gil' => 'ki',
            'gl' => 'es',
            'gsw' => 'ch',
            'gu' => 'in',
            'ha' => 'ng',
            'he' => 'il',
            'hi' => 'in',
            'hr' => 'hr',
            'sh' => 'rs',
            'hsb' => 'de',
            'hu' => 'hu',
            'hy' => 'am',
            'id' => 'id',
            'ig' => 'ng',
            'ii' => 'cn',
            'is' => 'is',
            'it' => 'it',
            'iu' => 'ca',
            'ja' => 'jp',
            'ka' => 'ge',
            'kk' => 'kz',
            'kl' => 'gl',
            'km' => 'kh',
            'kn' => 'in',
            'ko' => 'kr',
            'kok' => 'in',
            'ku' => 'iq',
            'ky' => 'kg',
            'lb' => 'lu',
            'lo' => 'la',
            'lt' => 'lt',
            'lv' => 'lv',
            'mi' => 'nz',
            'mk' => 'mk',
            'ml' => 'in',
            'mn' => 'mn',
            'moh' => 'ca',
            'mr' => 'in',
            'ms' => 'my',
            'mt' => 'mt',
            'my' => 'mm',
            'nb' => 'no',
            'ne' => 'np',
            'nl' => 'nl',
            'nn' => 'no',
            'no' => 'no',
            'oc' => 'fr',
            'or' => 'in',
            'pap' => 'an',
            'pa' => 'in',
            'pl' => 'pl',
            'prs' => 'af',
            'ps' => 'af',
            'pt' => 'pt',
            'quc' => 'gt',
            'qu' => 'pe',
            'rm' => 'ch',
            'ro' => 'ro',
            'ru' => 'ru',
            'rw' => 'rw',
            'sa' => 'in',
            'sah' => 'ru',
            'se' => 'no',
            'si' => 'lk',
            'sk' => 'sk',
            'sl' => 'si',
            'sma' => 'se',
            'smj' => 'se',
            'smn' => 'fi',
            'sms' => 'fi',
            'sq' => 'al',
            'sr' => 'rs',
            'st' => 'za',
            'sv' => 'se',
            'sw' => 'ke',
            'syc' => 'sy',
            'ta' => 'in',
            'te' => 'in',
            'tg' => 'tj',
            'th' => 'th',
            'tk' => 'tm',
            'tn' => 'bw',
            'tr' => 'tr',
            'tt' => 'ru',
            'tzm' => 'ma',
            'ug' => 'cn',
            'uk' => 'ua',
            'ur' => 'pk',
            'uz' => 'uz',
            'vi' => 'vn',
            'wo' => 'sn',
            'xh' => 'za',
            'yo' => 'ng',
            'zh' => 'cn',
            'zu' => 'za',
        ];

        $parts = explode('-', $ietf_code);
        $language_code = $parts[0];
        $country_code = $language_map[$language_code] ?? null;

        if ($country_code) {
            return $language_code . '_' . $country_code;
        } else {
            return null;
        }
    }

    private function hasAcceptedTerms($user_id)
    {
        $stmt = $this->db->prepare('SELECT accepted FROM terms_acceptance WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        return $result && $result['accepted'];
    }

    private function saveTermsAcceptance($user_id)
    {
        $stmt = $this->db->prepare('REPLACE INTO terms_acceptance (user_id, accepted, accepted_at) VALUES (:user_id, 1, datetime("now"))');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    private function sendWelcomeMessage($chat_id, $language_code)
    {
        $welcome_prompt = [
            [
                'role' => 'system',
                'content' => "You are Cohere's Coral, a friendly and helpful AI assistant. Your task is to generate a warm, engaging welcome message in {$language_code}. The message should be concise (2-3 sentences max) and make the user feel welcomed. If the language code is not recognized, respond in English only.",
            ],
            [
                'role' => 'user',
                'content' => 'Write a friendly welcome message for a new user who just started using our Telegram bot. Make it warm and inviting.',
            ],
        ];

        $this->simulateTyping($chat_id);

        $welcome_response = $this->cohere_client->chat($welcome_prompt, 'command-r-plus');
        $welcome_message = $welcome_response['message']['content'][0]['text'];

        $this->simulateTyping($chat_id, strlen($welcome_message));
        $this->telegram->sendMessage($chat_id, $welcome_message);
    }

    private function sendTermsAcceptanceMessage($chat_id, $language_code)
    {
        $terms_prompt = [
            [
                'role' => 'system',
                'content' => "Create a terms acceptance message in {$language_code}. The message must:
                 1. State the requirement to review [Terms of Use](https://cohere.com/terms-of-use) and [Privacy Policy](https://cohere.com/privacy) using Markdown links
                 2. Briefly explain the purpose of accepting these documents
                 3. Be concise - maximum 2-3 sentences
                 4. Avoid greetings, thanks, or farewell phrases
                 If the language is not recognized, respond in English only.",
            ],
            [
                'role' => 'user',
                'content' => 'Create a straightforward message about terms acceptance. Focus only on the documents and their importance.',
            ],
        ];

        $this->simulateTyping($chat_id);

        $terms_response = $this->cohere_client->chat($terms_prompt, 'command-r-plus');
        $terms_message = $terms_response['message']['content'][0]['text'];

        $button_prompt = [
            [
                'role' => 'system',
                'content' => "Generate a single, clear call-to-action button text in {$language_code} for accepting terms of use. The text should:
                 1. Be very short (1-3 words maximum)
                 2. Be action-oriented
                 3. Clearly indicate acceptance/agreement
                 4. Absolutely no surrounding quotes or formatting
                 5. Ensure that the text is not enclosed in any type of quotation marks
                 If the language is not recognized, respond in English only.",
            ],
            [
                'role' => 'user',
                'content' => 'Generate a short, clear button text for accepting terms and conditions without any surrounding quotes or formatting. Absolutely no quotation marks should be included. Make it concise and action-oriented.',
            ],
        ];

        $this->simulateTyping($chat_id);

        $button_response = $this->cohere_client->chat($button_prompt, 'command-r-plus');
        $button_text = $button_response['message']['content'][0]['text'];

        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
            [
                ['text' => $button_text, 'callback_data' => 'accept_terms'],
            ],
        ]);

        $this->simulateTyping($chat_id, strlen($terms_message));
        $this->telegram->sendMessage($chat_id, $terms_message, 'Markdown', false, null, $keyboard);
    }

    private function handleStartCommand($chat_id, $language_code)
    {
        $this->sendWelcomeMessage($chat_id, $language_code);

        if (! $this->hasAcceptedTerms($chat_id)) {
            $this->sendTermsAcceptanceMessage($chat_id, $language_code);
        }
    }

    private function handleCallbackQuery($callback_query)
    {
        $chat_id = $callback_query['message']['chat']['id'];
        $callback_id = $callback_query['id'];
        $language_code = $this->convert_language_code($callback_query['from']['language_code'] ?? 'en');

        if ('accept_terms' === $callback_query['data']) {
            if (! $this->hasAcceptedTerms($chat_id)) {
                $this->telegram->answerCallbackQuery($callback_id);
                $this->saveTermsAcceptance($chat_id);

                $success_prompt = [
                    [
                        'role' => 'system',
                        'content' => "Generate a success message in {$language_code} language for accepting terms of use. The message should:
                         1. Confirm successful acceptance of terms
                         2. Thank the user for joining
                         3. Clearly explain that they can now start chatting with Cohere's Coral AI by sending any message
                         4. Format the message in two paragraphs for better readability
                         If the language is not recognized, respond in English only.",
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Generate a message confirming successful acceptance of terms and explaining next steps',
                    ],
                ];

                $this->simulateTyping($chat_id);

                $success_response = $this->cohere_client->chat($success_prompt, 'command-r-plus');
                $success_message = $success_response['message']['content'][0]['text'];

                $this->simulateTyping($chat_id, strlen($success_message));
                $this->telegram->sendMessage($chat_id, $success_message);
            } else {
                $this->sendWelcomeMessage($chat_id, $language_code);
            }
        }
    }

    private function getUpdates()
    {
        $response = file_get_contents(
            "https://api.telegram.org/bot{$this->telegram_token}/getUpdates?" .
            http_build_query([
                'offset' => $this->update_id,
                'timeout' => 30,
            ])
        );

        if (false === $response) {
            throw new \Exception('Error getting updates');
        }

        $data = json_decode($response, true);

        if (! $data['ok']) {
            throw new \Exception('Telegram API error: ' . ($data['description'] ?? 'Unknown error'));
        }

        return $data['result'];
    }

    private function getUserContext($user_id)
    {
        $MAX_CONTEXT_MESSAGES = 10;
        $MAX_CONTEXT_LENGTH = 2000;

        $stmt = $this->db->prepare('SELECT messages FROM user_context WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        $messages = $result ? json_decode($result['messages'], true) : [];
        $validMessages = [];
        $contextLength = 0;
        $messageCount = 0;

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $message = $messages[$i];

            if (! isset($message['role']) || ! isset($message['message']) || '' === trim($message['message'])) {
                continue;
            }

            $messageLength = strlen($message['message']);

            if ($messageCount >= $MAX_CONTEXT_MESSAGES || $contextLength + $messageLength > $MAX_CONTEXT_LENGTH) {
                break;
            }

            array_unshift($validMessages, [
                'role' => $message['role'],
                'content' => $message['message'],
            ]);

            $contextLength += $messageLength;
            $messageCount++;
        }

        array_unshift($validMessages, [
            'role' => 'system',
            'content' => "You are Cohere's Coral, a helpful AI assistant. Keep context of the conversation and provide relevant responses.",
        ]);

        return $validMessages;
    }

    private function saveUserContext($user_id, $messages)
    {
        $MAX_STORED_MESSAGES = 20;

        if (count($messages) > $MAX_STORED_MESSAGES) {
            $messages = array_slice($messages, -$MAX_STORED_MESSAGES);
        }

        $stmt = $this->db->prepare('REPLACE INTO user_context (user_id, messages) VALUES (:user_id, :messages)');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':messages', json_encode($messages), SQLITE3_TEXT);
        $stmt->execute();
    }

    private function cleanupOldContexts()
    {
        $stmt = $this->db->prepare('DELETE FROM user_context WHERE datetime("now", "-24 hours") > last_updated');
        $stmt->execute();
    }

    private function getCohereResponse($user_context, $chat_id)
    {
        try {
            $this->logDebug('User context before API call:', $user_context);

            $this->simulateTyping($chat_id);

            $response = $this->cohere_client->chat($user_context, 'command-r-plus');

            $this->logDebug('Raw Cohere response:', $response);

            if (isset($response['message']['content']) && is_array($response['message']['content']) && ! empty($response['message']['content'])) {
                $responseText = '';
                foreach ($response['message']['content'] as $contentPart) {
                    if (isset($contentPart['text'])) {
                        $responseText .= $contentPart['text'];
                    }
                }

                $user_context[] = ['role' => 'assistant', 'message' => $responseText];
                $this->saveUserContext($chat_id, $user_context);

                $formattedResponse = $this->formatResponse($responseText);

                $this->simulateTyping($chat_id, strlen($formattedResponse));

                return ['text' => $formattedResponse, 'parse_mode' => 'HTML'];
            } else {
                $this->logDebug('Unexpected response format:', $response);

                return ['text' => 'Sorry, I couldn\'t generate a response. Please try again later.'];
            }
        } catch (\Exception $e) {
            $this->logDebug('Cohere API error:', ['message' => $e->getMessage()]);

            return ['text' => 'Error when calling Cohere API: ' . $e->getMessage()];
        }
    }

    private function simulateTyping($chat_id, $messageLength = null)
    {
        $baseDelay = 1;

        if ($messageLength) {
            $additionalDelay = min(ceil($messageLength / 100), 4);
            $totalDelay = $baseDelay + $additionalDelay;

            $intervals = ceil($totalDelay / 4);
            for ($i = 0; $i < $intervals; $i++) {
                $this->telegram->sendChatAction($chat_id, 'typing');
                sleep(4);
            }
        } else {
            $this->telegram->sendChatAction($chat_id, 'typing');
            sleep($baseDelay);
        }
    }

    private function logDebug($message, $data)
    {
        $log_entry = date('Y-m-d H:i:s') . ' - ' . $message . ': ' . print_r($data, true) . "\n";
        file_put_contents('debug.log', $log_entry, FILE_APPEND);
    }

    private function formatResponse($text)
    {
        $codeBlocks = [];
        $text = preg_replace_callback('/```(\w+)?\n(.*?)\n```/s', function ($matches) use (&$codeBlocks) {
            $placeholder = "\x0001CODE" . count($codeBlocks) . "\x0001";
            $language = isset($matches[1]) ? $matches[1] : '';
            $code = $matches[2];
            $codeBlocks[] = "<pre language=\"{$language}\">" .
                            htmlspecialchars($code) .
                            '</pre>';

            return $placeholder;
        }, $text);

        $inlineCodes = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($matches) use (&$inlineCodes) {
            $placeholder = "\x0002INLINE" . count($inlineCodes) . "\x0002";
            $inlineCodes[] = "<code>{$matches[1]}</code>";

            return $placeholder;
        }, $text);

        $formatters = [
            '**' => ['tag' => 'b', 'pattern' => '/\*\*(.*?)\*\*/'],
            '__' => ['tag' => 'b', 'pattern' => '/__(.*?)__/'],
            '*' => ['tag' => 'i', 'pattern' => '/\*(.*?)\*/'],
            '_' => ['tag' => 'i', 'pattern' => '/_(.*?)_/'],
            '~~' => ['tag' => 's', 'pattern' => '/~~(.*?)~~/'],
            '~' => ['tag' => 's', 'pattern' => '/~(.*?)~/'],
        ];

        $findDeepestMarkup = function ($text) use ($formatters) {
            $positions = [];
            foreach ($formatters as $marker => $info) {
                if (preg_match($info['pattern'], $text, $matches, PREG_OFFSET_CAPTURE)) {
                    $positions[] = [
                        'start' => $matches[0][1],
                        'length' => strlen($matches[0][0]),
                        'content' => $matches[1][0],
                        'marker' => $marker,
                        'tag' => $info['tag'],
                    ];
                }
            }

            if (empty($positions)) {
                return null;
            }

            usort($positions, function ($a, $b) {
                return $b['start'] - $a['start'];
            });

            return $positions[0];
        };

        $processFormatting = function ($text) use (&$processFormatting, $findDeepestMarkup) {
            $markup = $findDeepestMarkup($text);

            if (null === $markup) {
                return $text;
            }

            $before = substr($text, 0, $markup['start']);
            $after = substr($text, $markup['start'] + $markup['length']);

            $content = $processFormatting($markup['content']);

            $replacement = "<{$markup['tag']}>{$content}</{$markup['tag']}>";

            return $processFormatting($before . $replacement . $after);
        };

        $text = $processFormatting($text);

        $lines = explode("\n", $text);
        $formattedLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $matches)) {
                $formattedLines[] = " {$matches[1]}";
            } elseif (preg_match('/^\s*(\d+)\.\s+(.+)$/', $line, $matches)) {
                $formattedLines[] = "{$matches[1]}. {$matches[2]}";
            } else {
                $formattedLines[] = $line;
            }
        }

        $text = implode("\n", $formattedLines);

        foreach ($codeBlocks as $i => $block) {
            $text = str_replace("\x0001CODE{$i}\x0001", $block, $text);
        }

        foreach ($inlineCodes as $i => $code) {
            $text = str_replace("\x0002INLINE{$i}\x0002", $code, $text);
        }

        return $text;
    }
}

$config = [
    'telegram_token' => 'TELEGRAM_TOKEN',
    'cohere_api_key' => 'COHERE_API_KEY',
];

$bot = new CohereTelegramBot($config['telegram_token'], $config['cohere_api_key']);
$bot->run();
