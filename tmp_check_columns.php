<?php
$c=new mysqli('localhost','root','','inventaris_barang');
if($c->connect_error){die($c->connect_error);}
$r=$c->query('SHOW COLUMNS FROM produk');
while($row=$r->fetch_assoc()){
    echo $row['Field'] . ' ' . $row['Type'] . ' ' . $row['Null'] . ' ' . $row['Default'] . "\n";
}
if($c->error) echo 'ERR:'.$c->error;
?>