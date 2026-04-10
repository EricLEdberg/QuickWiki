<?php

require 'clsFileGrid.php';

$config = json_decode(file_get_contents('FileGridConfig.json'), true);
$root   = realpath($config['root_folder']);
$file   = realpath($_GET['f'] ?? '');

if (!$file || strpos($file, $root) !== 0 || !is_file($file)) {
    http_response_code(403);
    exit('Access denied');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);

exit;
