<?php
function parse_wbs_to_tasks(string $wbsText): array
{
    $lines = preg_split('/\r\n|\r|\n/', $wbsText);
    $tasks = [];

    foreach ($lines as $line) {
        $line = trim($line);

        // bỏ các dòng tiêu đề kiểu ### ...
        $line = preg_replace('/^\#{1,6}\s*/', '', $line);

        // bắt các dạng: 1.1 Tên | 1.1: Tên | - 1.1 Tên | 1.1) Tên | 1.1- Tên
        if (preg_match('/^[-•\s]*\d+(?:\.\d+)+\s*[:\)\-]?\s*(.+)$/u', $line, $m)) {
            $title = trim($m[1]);
            if ($title !== '') $tasks[] = $title;
        }
    }

    // loại trùng
    $tasks = array_values(array_unique($tasks));
    return $tasks;
}