<?php
header('Content-Type: text/plain');
$log_file = '/tmp/ransomware_debug.log';
if (file_exists($log_file)) {
    echo file_get_contents($log_file);
} else {
    echo "ログファイルが見つかりません: " . $log_file;
}
?>