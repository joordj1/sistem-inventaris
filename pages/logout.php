<?php

// Hapus semua sesi
session_unset();

// Hancurkan sesi
session_destroy();

// Menampilkan SweetAlert
echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Logout Berhasil',
            text: 'Anda telah berhasil logout.',
            showConfirmButton: false,
            timer: 1500
        }).then(function() {
            window.location.href = 'login.php';
        });
      </script>";

exit;
?>
