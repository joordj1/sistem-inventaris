// Fungsi konfirmasi penghapusan gudang dengan SweetAlert2
function confirmDeleteGudang(id_gudang) {
    // Lakukan pengecekan apakah gudang memiliki produk terkait
    fetch(`actions/cek_produk_in_gudang.php?id_gudang=${id_gudang}`)
        .then(response => response.json())
        .then(data => {
            if (data.hasProduk) {
                // Jika gudang memiliki produk, tampilkan peringatan
                Swal.fire({
                    icon: 'error',
                    title: 'Tidak Bisa Dihapus',
                    text: 'Tidak bisa menghapus data gudang dikarenakan terdapat produk pada gudang ini, pindahkan terlebih dahulu produk tersebut ke gudang lain.',
                    showConfirmButton: true
                });
            } else {
                // Jika tidak ada produk, tampilkan konfirmasi penghapusan
                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: "Gudang ini akan dihapus",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, Hapus!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Lakukan permintaan AJAX untuk menghapus gudang
                        fetch(`delete/hapus_gudang.php?id_gudang=${id_gudang}&redirect=data_gudang`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Terhapus!',
                                        text: 'Gudang berhasil dihapus.',
                                        showConfirmButton: true
                                    }).then(() => {
                                        location.reload(); // Refresh halaman setelah berhasil menghapus
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Gagal',
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
