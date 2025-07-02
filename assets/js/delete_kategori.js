// Fungsi konfirmasi penghapusan dengan SweetAlert2
function confirmDelete(id_kategori) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Kategori ini akan dihapus",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Lakukan permintaan AJAX untuk menghapus kategori
            fetch(`delete/hapus_kategori.php?id_kategori=${id_kategori}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: 'Kategori berhasil dihapus.',
                            showConfirmButton: true
                        }).then(() => {
                            location.reload(); // Refresh halaman setelah berhasil menghapus
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Tidak Bisa Dihapus',
                            text: data.message,
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