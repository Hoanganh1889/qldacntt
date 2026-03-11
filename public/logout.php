<?php
session_start();

/* XÓA TOÀN BỘ SESSION */
session_unset();
session_destroy();

/* QUAY VỀ LOGIN */
header("Location: login.php");
exit;
