<?php
define('OPENROUTER_API_KEY', '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');

define('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1/chat/completions');

/*
 Model gợi ý (ổn định):
 - openai/gpt-4o-mini
 - openai/gpt-3.5-turbo
 - anthropic/claude-3-haiku
*/
define('OPENROUTER_MODEL', 'openai/gpt-4o-mini');

function call_openrouter(string $prompt): string
{
    $payload = [
        'model' => OPENROUTER_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là chuyên gia quản lý dự án CNTT, phân tích dự án theo WBS rõ ràng.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.4
    ];

    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
            'Content-Type: application/json',
            'HTTP-Referer: http://localhost',
            'X-Title: QLDACNTT'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OpenRouter API lỗi ($httpCode): $response");
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? '';
}