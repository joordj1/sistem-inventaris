<?php
require_auth_roles(['admin', 'petugas', 'viewer'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=dashboard',
]);

include __DIR__ . '/../views/histori_log.php';
