<?php

class FileGrid
{
    private string $rootFolder;
    private array $config;
    private array $errors = [];

    public function __construct(string $configFile)
    {
        if (file_exists($configFile)) {
           //  echo "Config file exits: " . $configFile . "<br>";
        } else {
            throw new RuntimeException("Config file not found: {$configFile} <br>");
            exit;
        }
        
        $this->config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in config: " . json_last_error_msg() . "<br> ");
            exit;
        }
        
        # Note: realpath() will fail if PHP does not have permission to access the path, or if the path does not exist. Ensure the path is correct and accessible.
        $this->rootFolder = realpath($this->config['root_folder']);

        if (!$this->rootFolder || !is_dir($this->rootFolder)) {
            echo "config rootFolder: " . $this->config['root_folder'] . "<br>";
            throw new RuntimeException("Invalid root folder: permissions or path invalid");
            exit;
        }
    }

    /**
     * Scan the root folder and return validated metadata as JSON array
     */
    public function scan(): array
    {
        $items = [];
        $dir = new DirectoryIterator($this->rootFolder);

        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }

            try {
                $meta = $this->inspectFile($file);
                if ($meta !== null) {
                    $items[] = $meta;
                }
            } catch (Exception $e) {
                $this->logError($e->getMessage());
            }
        }

        return $items;
    }

    /**
     * Validate, inspect, and classify file
     */
    private function inspectFile(SplFileInfo $file): ?array
    {
        $realPath = realpath($file->getPathname());

        // ROOT folder confinement
        if (strpos($realPath, $this->rootFolder) !== 0) {
            throw new RuntimeException("File outside root: {$realPath}");
        }

        $filename = $file->getFilename();

        // -----------------------------------------------------
        // DO NOT DISPLAY filters (regex)
        // -----------------------------------------------------
        foreach ($this->config['do_not_display']['filenames'] as $pattern) {
            $xPat = preg_quote($pattern, '/');
            if (preg_match("/{$xPat}/i", $filename)) {
                return null;
            }
        }
        foreach ($this->config['do_not_display']['extensions'] as $pattern) {
            $xPat = preg_quote($pattern, '/');
            if (preg_match("/{$xPat}/i", $filename)) {
                return null;
            }
        }

        // -----------------------------------------------------
        // File property inspection
        // -----------------------------------------------------
        $mime = mime_content_type($realPath);
        $size = $file->getSize();

        if ($size === false || $size < 0) {
            throw new RuntimeException("Invalid file size: {$filename}");
        }

        // Spoofing detection (basic)
        if (in_array($mime, $this->config['allowed_image_types'], true)) {
            $imageInfo = @getimagesize($realPath);
            if ($imageInfo === false) {
                throw new RuntimeException("Image spoofing detected: {$filename}");
            }
        }

        return [
            'name'       => $filename,
            'size'       => $size,
            'mime'       => $mime,
            'is_image'   => in_array($mime, $this->config['allowed_image_types'], true),
            'created'    => date('Y-m-d H:i:s', $file->getCTime()),
            'modified'   => date('Y-m-d H:i:s', $file->getMTime()),
            'path'       => $realPath
        ];
    }

    /**
     * Render paginated HTML grid
     */
    public function renderGrid(array $items, string $divId, int $page = 1): void
    {
        $perPage = $this->config['items_per_page'];
        $totalPages = max(1, ceil(count($items) / $perPage));
        $page = max(1, min($page, $totalPages));

        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);

        echo "<div id='{$divId}'>";
        echo $this->renderPagination($page, $totalPages);

        echo "<table class='file-grid'><tr>";

        foreach ($pageItems as $item) {
            echo "<td class='file-cell'>";
            if ($item['is_image']) {
                $src = htmlspecialchars($item['path']);
                echo "<img src='FileGridDownloader.php?f=" . urlencode($item['path']) . "' class='thumb'>";
            } else {
                echo "<div class='binary-file'>📦</div>";
            }

            echo "<div class='meta'>";
            echo "<strong>{$item['name']}</strong><br>";
            echo round($item['size'] / 1024, 2) . " KB<br>";
            echo $item['modified'];
            echo "</div>";

            echo "<div class='download'>";
            echo "<a href='FileGridDownloader.php?f=" . urlencode($item['path']) . "'>Download</a>";
            echo "</div>";

            echo "</td>";
        }

        echo "</tr></table>";
        echo "</div>";
    }

    private function renderPagination(int $page, int $totalPages): string
    {
        return "
        <div class='pagination'>
            <a href='?page=1'>First</a>
            <a href='?page=" . max(1, $page - 1) . "'>Back</a>
            Page {$page} of {$totalPages}
            <a href='?page=" . min($totalPages, $page + 1) . "'>Next</a>
            <a href='?page={$totalPages}'>Last</a>
        </div>";
    }

    private function logError(string $message): void
    {
        file_put_contents(
            $this->config['log_file'],
            date('c') . " - {$message}<br>",
            FILE_APPEND
        );
    }
}
