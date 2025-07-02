// Fungsi konfirmasi penghapusan dengan SweetAlert2
function confirmDeleteProduk(id_produk) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Produk ini akan dihapus",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Lakukan permintaan AJAX untuk menghapus produk
            fetch(`delete/hapus_produk.php?id_produk=${id_produk}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: 'Produk berhasil dihapus.',
                            showConfirmButton: true
                        }).then(() => {
                            location.reload(); // Refresh halaman setelah berhasil menghapus
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: 'Terjadi kesalahan saat menghapus produk.',
                            showConfirmButton: true
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Terjadi kesalahan, silakan coba lagi.',
                        showConfirmButton: true
                    });
                });
        }
    });
}
