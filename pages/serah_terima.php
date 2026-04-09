<?php
require_auth_roles(['admin', 'petugas', 'user'], [
    'login_redirect' => 'login.php',
    'forbidden_redirect' => 'index.php?page=dashboard',
]);

$viewMode = $_GET['view'] ?? '';
$actionMode = $_GET['action'] ?? '';

if ($viewMode === 'detail') {
    include __DIR__ . '/../views/serah_terima_info.php';
    return;
}

if ($actionMode === 'form') {
    if (!inventory_user_can_manage()) {
        $_GET['error'] = 'Anda tidak memiliki akses';
        include __DIR__ . '/../views/serah_terima_barang.php';
        return;
    }
    include __DIR__ . '/../views/serah_terima_form.php';
    return;
}

include __DIR__ . '/../views/serah_terima_barang.php';
