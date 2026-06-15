<?php
// limpiar_cache.php
$files = glob(__DIR__ . '/cache/cache_*.txt');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
echo "OK";
?>
