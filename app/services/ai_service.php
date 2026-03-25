<?php
require_once __DIR__ . '/../../config/ai.php';

function ai_analyze_project(array $payload): array
{
    $prompt = <<<PROMPT
Bạn là AI quản lý dự án CNTT.

Dựa trên thông tin sau, hãy:
1. Phân tích dự án
2. Sinh danh sách công việc (task) hợp lý

YÊU CẦU TRẢ VỀ DẠNG JSON CHUẨN, KHÔNG GIẢI THÍCH, KHÔNG MARKDOWN:

{
  "summary": "...",
  "risk_level": "Thấp|Trung bình|Cao",
  "tasks": [
    {
      "title": "...",
      "priority": "Thấp|Trung bình|Cao",
      "due_days": 5
    }
  ]
}

THÔNG TIN DỰ ÁN:
Tên: {$payload['name']}
Mục tiêu: {$payload['goal']}
Phạm vi: {$payload['scope']}
Thời gian: {$payload['start_date']} → {$payload['end_date']}
Ngân sách: {$payload['budget']}
PROMPT;

    $data = [
        "model" => OPENROUTER_MODEL,
        "messages" => [
            ["role" => "system", "content" => "Bạn là AI quản lý dự án chuyên nghiệp."],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.4,
    ];

    $ch = curl_init(OPENROUTER_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . OPENROUTER_API_KEY,
            "HTTP-Referer: http://localhost", // BẮT BUỘC
            "X-Title: QLDACNTT Project AI"
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception("cURL error: " . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OpenRouter HTTP $httpCode: $response");
    }

    $json = json_decode($response, true);

    if (!isset($json['choices'][0]['message']['content'])) {
        throw new Exception("OpenRouter response invalid");
    }

    $content = trim($json['choices'][0]['message']['content']);

    // Parse JSON AI trả về
    $result = json_decode($content, true);

    if (!is_array($result) || !isset($result['tasks'])) {
        throw new Exception("AI không trả JSON hợp lệ: " . $content);
    }

    return $result;
}
