<?php
require_auth_roles(['admin', 'petugas', 'user'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=dashboard',
]);

include __DIR__ . '/../views/report_persediaan_user.php';