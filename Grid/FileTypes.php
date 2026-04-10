<?php

function lookupFileTypeSupport(
    string $jsonFile,
    ?string $mimeType = null,
    ?string $filename = null
): ?array {

    if (!file_exists($jsonFile)) {
        throw new RuntimeException("JSON file not found");
    }

    $data = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON structure");
    }

    $extension = null;
    if ($filename !== null) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    $mimeType = $mimeType !== null ? strtolower(trim($mimeType)) : null;

    foreach ($data as $entry) {
        if (
            ($mimeType && in_array($mimeType, $entry['mime_types'], true)) ||
            ($extension && in_array($extension, $entry['extensions'], true))
        ) {
            return $entry;
        }
    }

    return null;
}


$result = lookupFileTypeSupport(
    __DIR__ . '/FileTypes.json',
    'image/jpeg',
    'photo.jpg'
);

if ($result !== null) {
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    echo "No matching file type found.";
}
