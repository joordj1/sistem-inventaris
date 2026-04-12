<?php
$url = 'https://chart.googleapis.com/chart?cht=qr&chs=256x256&chl=test&choe=UTF-8';
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo 'curl:' . $code . ':' . (($data !== false) ? strlen($data) : 0);
} else {
    echo 'curl:not_available';
}
?>
