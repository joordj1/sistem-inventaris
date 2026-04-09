<?php
$activePage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$sidebarMenu = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi bi-speedometer2', 'link' => 'index.php?page=dashboard'],
    ['key' => 'data_produk', 'label' => 'Data Barang', 'icon' => 'bi bi-box-seam', 'link' => 'index.php?page=data_produk'],
    ['key' => 'kategori_barang', 'label' => 'Kategori Barang', 'icon' => 'bi bi-tags', 'link' => 'index.php?page=kategori_barang'],
    ['key' => 'data_gudang', 'label' => 'Lokasi / Gudang', 'icon' => 'bi bi-building', 'link' => 'index.php?page=data_gudang'],
    ['key' => 'laporan', 'label' => 'Laporan', 'icon' => 'bi bi-file-earmark-text', 'link' => 'index.php?page=laporan'],
    ['key' => 'histori_log', 'label' => 'Histori Log', 'icon' => 'bi bi-clock-history', 'link' => 'index.php?page=histori_log'],
];

if (!inventory_user_is_view_only()) {
    $sidebarMenu[] = ['key' => 'barang_masuk', 'label' => 'Barang Masuk', 'icon' => 'bi bi-box-arrow-in-down', 'link' => 'index.php?page=barang_masuk'];
    $sidebarMenu[] = ['key' => 'barang_keluar', 'label' => 'Barang Keluar', 'icon' => 'bi bi-box-arrow-up', 'link' => 'index.php?page=barang_keluar'];
    $sidebarMenu[] = ['key' => 'mutasi_barang', 'label' => 'Mutasi Barang', 'icon' => 'bi bi-arrow-left-right', 'link' => 'index.php?page=mutasi_barang'];
    $sidebarMenu[] = ['key' => 'serah_terima', 'label' => 'Serah Terima', 'icon' => 'bi bi-clipboard-check', 'link' => 'index.php?page=serah_terima'];
}

if (isset($userMenuVisible) && $userMenuVisible) {
    $sidebarMenu[] = ['key' => 'user', 'label' => 'Manajemen User', 'icon' => 'bi bi-people', 'link' => 'index.php?page=user'];
}

$sidebarMenu[] = ['key' => 'logout', 'label' => 'Keluar', 'icon' => 'bi bi-box-arrow-right', 'link' => 'index.php?page=logout'];
?>

<aside class="app-sidebar" aria-label="Sidebar navigasi">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <i class="bi bi-stack" aria-hidden="true"></i>
            <span>Inventaris PLN UP Brantas</span>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Menu utama">
        <?php foreach ($sidebarMenu as $item):
            $isActive = $activePage === $item['key'];
            $itemClass = 'sidebar-item' . ($isActive ? ' active' : '');
            ?>
            <a href="<?= $item['link']; ?>" class="<?= $itemClass; ?>" aria-current="<?= $isActive ? 'page' : 'false'; ?>">
                <i class="<?= $item['icon']; ?>"></i>
                <span><?= $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <p class="sidebar-footer-title">Butuh Bantuan?</p>
        <a href="#" class="sidebar-link">Hubungi tim support</a>
    </div>
</aside>
