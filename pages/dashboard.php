    <?php
// Statistik ringkas
$totalBarang = 0;
$barangMasuk = 0;
$barangKeluar = 0;
$totalKategori = 0;
$totalGudang = 0;
$totalMutasi = 0;
$totalHandoverAktif = 0;

if (isset($koneksi)) {
    $query = $koneksi->query("SELECT COUNT(*) AS total from produk");
    if ($query) $totalBarang = $query->fetch_assoc()['total'];

    $query = $koneksi->query("SELECT COALESCE(SUM(jumlah),0) AS total FROM stoktransaksi WHERE tipe_transaksi = 'masuk'");
    if ($query) $barangMasuk = $query->fetch_assoc()['total'];

    $query = $koneksi->query("SELECT COALESCE(SUM(jumlah),0) AS total FROM stoktransaksi WHERE tipe_transaksi = 'keluar'");
    if ($query) $barangKeluar = $query->fetch_assoc()['total'];

    $query = $koneksi->query("SELECT COUNT(*) AS total FROM kategori");
    if ($query) $totalKategori = $query->fetch_assoc()['total'];

    $query = $koneksi->query("SELECT COUNT(*) AS total FROM gudang");
    if ($query) $totalGudang = $query->fetch_assoc()['total'];

    if (schema_table_exists_now($koneksi, 'mutasi_barang')) {
        $query = $koneksi->query("SELECT COUNT(*) AS total FROM mutasi_barang");
        if ($query) $totalMutasi = $query->fetch_assoc()['total'];
    }

    if (schema_table_exists_now($koneksi, 'serah_terima_barang')) {
        $query = $koneksi->query("SELECT COUNT(*) AS total FROM serah_terima_barang WHERE status = 'aktif'");
        if ($query) $totalHandoverAktif = $query->fetch_assoc()['total'];
    }

    // Data chart 1 per bulan
    $chartBulanan = [];
    $numericBulanan = [];
    $sql = "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan, tipe_transaksi, COALESCE(SUM(jumlah),0) AS total FROM stoktransaksi GROUP BY 1,2 ORDER BY 1 ASC";
    $result = $koneksi->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bulan = $row['bulan'];
            $jenis = $row['tipe_transaksi'];
            $total = (int) $row['total'];
            if (!isset($chartBulanan[$bulan])) {
                $chartBulanan[$bulan] = ['masuk' => 0, 'keluar' => 0];
            }
            if ($jenis === 'masuk') {
                $chartBulanan[$bulan]['masuk'] = $total;
            } else {
                $chartBulanan[$bulan]['keluar'] = $total;
            }
        }
    }

    // Data chart kategori sinkron langsung dari master kategori dan produk.
    $chartKategori = [];
    $sql = "SELECT k.nama_kategori, COUNT(p.id_produk) AS total
            FROM kategori k
            LEFT JOIN produk p ON p.id_kategori = k.id_kategori
            GROUP BY k.id_kategori, k.nama_kategori
            ORDER BY k.nama_kategori ASC";
    $result = $koneksi->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chartKategori[$row['nama_kategori']] = (int) $row['total'];
        }
    }
} else {
    $chartBulanan = [];
    $chartKategori = [];
}

$labels = array_keys($chartBulanan);
$masukData = [];
$keluarData = [];
foreach ($chartBulanan as $bulan => $values) {
    $masukData[] = $values['masuk'];
    $keluarData[] = $values['keluar'];
}

$katLabels = array_keys($chartKategori);
$katData = array_values($chartKategori);
?>

<div class="dashboard-stats-grid" aria-label="Ringkasan statistik inventaris" style="display:flex; gap:14px; flex-wrap:nowrap; width:100%; align-items:stretch; margin-bottom:24px;">
    <div class="stats-card" style="flex:1 1 0; min-width:0; max-width:20%; min-height:104px; padding:12px 14px; border-radius:14px; display:flex; flex-direction:column; align-items:flex-start; justify-content:center; text-align:left; gap:6px;">
        <div class="stat-icon" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; margin:0; font-size:1.05rem; line-height:1;"><i class="bi bi-box-seam"></i></div>
        <div class="stat-value" style="font-size:1.9rem; font-weight:700; line-height:1; margin:0;"><?= number_format($totalBarang,0,',','.'); ?></div>
        <div class="stat-label" style="margin:0; font-size:0.82rem; line-height:1.25; text-align:left;">Total Barang</div>
    </div>
    <div class="stats-card" style="flex:1 1 0; min-width:0; max-width:20%; min-height:104px; padding:12px 14px; border-radius:14px; display:flex; flex-direction:column; align-items:flex-start; justify-content:center; text-align:left; gap:6px;">
        <div class="stat-icon" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; margin:0; font-size:1.05rem; line-height:1;"><i class="bi bi-box-arrow-in-down"></i></div>
        <div class="stat-value" style="font-size:1.9rem; font-weight:700; line-height:1; margin:0;"><?= number_format($barangMasuk,0,',','.'); ?></div>
        <div class="stat-label" style="margin:0; font-size:0.82rem; line-height:1.25; text-align:left;">Barang Masuk</div>
    </div>
    <div class="stats-card" style="flex:1 1 0; min-width:0; max-width:20%; min-height:104px; padding:12px 14px; border-radius:14px; display:flex; flex-direction:column; align-items:flex-start; justify-content:center; text-align:left; gap:6px;">
        <div class="stat-icon" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; margin:0; font-size:1.05rem; line-height:1;"><i class="bi bi-box-arrow-up"></i></div>
        <div class="stat-value" style="font-size:1.9rem; font-weight:700; line-height:1; margin:0;"><?= number_format($barangKeluar,0,',','.'); ?></div>
        <div class="stat-label" style="margin:0; font-size:0.82rem; line-height:1.25; text-align:left;">Barang Keluar</div>
    </div>
    <div class="stats-card" style="flex:1 1 0; min-width:0; max-width:20%; min-height:104px; padding:12px 14px; border-radius:14px; display:flex; flex-direction:column; align-items:flex-start; justify-content:center; text-align:left; gap:6px;">
        <div class="stat-icon" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; margin:0; font-size:1.05rem; line-height:1;"><i class="bi bi-tags"></i></div>
        <div class="stat-value" style="font-size:1.9rem; font-weight:700; line-height:1; margin:0;"><?= number_format($totalKategori,0,',','.'); ?></div>
        <div class="stat-label" style="margin:0; font-size:0.82rem; line-height:1.25; text-align:left;">Kategori Barang</div>
    </div>
    <div class="stats-card" style="flex:1 1 0; min-width:0; max-width:20%; min-height:104px; padding:12px 14px; border-radius:14px; display:flex; flex-direction:column; align-items:flex-start; justify-content:center; text-align:left; gap:6px;">
        <div class="stat-icon" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; margin:0; font-size:1.05rem; line-height:1;"><i class="bi bi-building"></i></div>
        <div class="stat-value" style="font-size:1.9rem; font-weight:700; line-height:1; margin:0;"><?= number_format($totalGudang,0,',','.'); ?></div>
        <div class="stat-label" style="margin:0; font-size:0.82rem; line-height:1.25; text-align:left;">Gudang</div>
    </div>
</div>

<?php if (schema_table_exists_now($koneksi, 'mutasi_barang') || schema_table_exists_now($koneksi, 'serah_terima_barang')): ?>
<div class="dashboard-stats-grid mb-4" aria-label="Ringkasan prioritas dua" style="display:flex; gap:14px; flex-wrap:wrap; width:100%; align-items:stretch;">
    <div class="stats-card" style="flex:1 1 280px; min-height:104px; padding:12px 14px; border-radius:14px; display:flex; flex-direction:column; justify-content:center; gap:6px;">
        <div class="stat-icon" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; font-size:1.05rem;"><i class="bi bi-arrow-left-right"></i></div>
        <div class="stat-value" style="font-size:1.9rem; font-weight:700; line-height:1;"><?= number_format($totalMutasi,0,',','.'); ?></div>
        <div class="stat-label" style="font-size:0.82rem; line-height:1.25;">Total Mutasi Resmi</div>
        <a href="index.php?page=mutasi_barang" class="small text-decoration-none">Buka modul mutasi</a>
    </div>
    <div class="stats-card" style="flex:1 1 280px; min-height:104px; padding:12px 14px; border-radius:14px; display:flex; flex-direction:column; justify-content:center; gap:6px;">
        <div class="stat-icon" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; font-size:1.05rem;"><i class="bi bi-clipboard-check"></i></div>
        <div class="stat-value" style="font-size:1.9rem; font-weight:700; line-height:1;"><?= number_format($totalHandoverAktif,0,',','.'); ?></div>
        <div class="stat-label" style="font-size:0.82rem; line-height:1.25;">Serah Terima Aktif</div>
        <a href="index.php?page=serah_terima" class="small text-decoration-none">Buka modul serah terima</a>
    </div>
</div>
<?php endif; ?>

<div class="chart-grid">
    <div class="chart-card">
        <h6>Barang Masuk vs Barang Keluar</h6>
        <div class="chart-frame">
            <canvas id="chartTransaksi"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h6>Distribusi Kategori Barang</h6>
        <div class="chart-frame">
            <canvas id="chartKategori"></canvas>
        </div>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-3 g-4">
    <div class="col">
        <a href="index.php?page=data_produk" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-clipboard-data display-1" style="color: var(--color-primary)"></i>
                    <h5 class="card-title mt-3">Data Produk</h5>
                </div>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="index.php?page=barang_masuk" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-box-arrow-in-down display-1" style="color: var(--color-success)"></i>
                    <h5 class="card-title mt-3">Barang Masuk</h5>
                </div>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="index.php?page=barang_keluar" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-box-arrow-up display-1" style="color: var(--color-info)"></i>
                    <h5 class="card-title mt-3">Barang Keluar</h5>
                </div>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="index.php?page=data_gudang" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-building display-1" style="color: var(--color-text-secondary)"></i>
                    <h5 class="card-title mt-3">Data Gudang</h5>
                </div>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="index.php?page=kategori_barang" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-tags display-1" style="color: var(--color-primary)"></i>
                    <h5 class="card-title mt-3">Kategori</h5>
                </div>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="index.php?page=transaksi_barang" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-receipt display-1" style="color: var(--color-warning)"></i>
                    <h5 class="card-title mt-3">Transaksi Barang</h5>
                </div>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="index.php?page=mutasi_barang" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-arrow-left-right display-1" style="color: var(--color-success)"></i>
                    <h5 class="card-title mt-3">Mutasi Barang</h5>
                </div>
            </div>
        </a>
    </div>

    <div class="col">
        <a href="index.php?page=serah_terima" class="text-decoration-none">
            <div class="card card-interactive h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-clipboard-check display-1" style="color: var(--color-info)"></i>
                    <h5 class="card-title mt-3">Serah Terima</h5>
                </div>
            </div>
        </a>
    </div>
</div>

<script>
  function renderDashboardCharts() {
    if (!window.dashboardChartState) {
      window.dashboardChartState = {
        transaksi: null,
        kategori: null
      };
    }

    if (window.dashboardChartState.transaksi) {
      window.dashboardChartState.transaksi.destroy();
      window.dashboardChartState.transaksi = null;
    }
    if (window.dashboardChartState.kategori) {
      window.dashboardChartState.kategori.destroy();
      window.dashboardChartState.kategori = null;
    }

    var transaksiCtx = document.getElementById('chartTransaksi').getContext('2d');
    window.dashboardChartState.transaksi = new Chart(transaksiCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($labels); ?>,
        datasets: [
          {
            label: 'Masuk',
            data: <?= json_encode($masukData); ?>,
            borderColor: 'rgba(13, 96, 208, 0.9)',
            backgroundColor: 'rgba(13, 96, 208, 0.2)',
            borderWidth: 2,
            tension: 0.35,
            pointRadius: 3,
            fill: true
          },
          {
            label: 'Keluar',
            data: <?= json_encode($keluarData); ?>,
            borderColor: 'rgba(246, 59, 59, 0.9)',
            backgroundColor: 'rgba(246, 59, 59, 0.2)',
            borderWidth: 2,
            tension: 0.35,
            pointRadius: 3,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(226,232,240,0.6)' }
          },
          x: {
            grid: { display: false }
          }
        },
        plugins: {
          legend: { position: 'top' },
          tooltip: { mode: 'index', intersect: false }
        }
      }
    });

    var kategoriCtx = document.getElementById('chartKategori').getContext('2d');
    window.dashboardChartState.kategori = new Chart(kategoriCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($katLabels); ?>,
        datasets: [{
          data: <?= json_encode($katData); ?>,
          backgroundColor: [
            'rgba(13,96,208,0.8)',
            'rgba(59,130,246,0.8)',
            'rgba(14,165,233,0.7)',
            'rgba(125,211,252,0.7)',
            'rgba(236,72,153,0.7)',
            'rgba(249,115,22,0.7)',
            'rgba(34,197,94,0.7)',
            'rgba(14,165,19,0.7)'
          ],
          borderWidth: 1,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8 } }
        }
      }
    });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    renderDashboardCharts();
  } else {
    document.addEventListener('DOMContentLoaded', renderDashboardCharts);
  }
</script>
