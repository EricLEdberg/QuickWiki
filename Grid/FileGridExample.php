<?php
require 'clsFileGrid.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$grid = new FileGrid(__DIR__ . '/FileGridConfig.json');
$items = $grid->scan();

?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .file-grid { width: 100%; border-collapse: collapse; }
        .file-cell { border: 1px solid #ccc; padding: 10px; text-align: center; }
        .thumb { max-width: 120px; max-height: 120px; }
        .pagination a { margin: 0 5px; }
    </style>
</head>
<body>

<?php
$grid->renderGrid($items, 'fileGrid', $page);
?>

</body>
</html>
