// Fungsi konfirmasi penghapusan dengan SweetAlert2
function confirmDeleteUser(id_user) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "User ini akan dinonaktifkan agar histori tetap aman",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Lakukan permintaan AJAX untuk menghapus user
            fetch(`delete/hapus_user.php?id_user=${id_user}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Dinonaktifkan!',
                            text: 'User berhasil dinonaktifkan secara aman.',
                            showConfirmButton: true
                        }).then(() => {
                            location.reload(); // Refresh halaman setelah berhasil menghapus
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: data.error || 'Terjadi kesalahan saat menghapus user.',
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
