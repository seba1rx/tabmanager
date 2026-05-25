<?php
$sessionsDir = __DIR__ . '/sessions';
if (!is_dir($sessionsDir)) {
    mkdir($sessionsDir, 0755);
}
session_save_path($sessionsDir);
