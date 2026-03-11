<?php
// app/services/notify.php

function notify(mysqli $conn, int $user_id, string $title, string $content = '', string $link = null): void {
    $stmt = $conn->prepare("
        INSERT INTO notifications(user_id, title, content, link)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $title, $content, $link);
    $stmt->execute();
}
