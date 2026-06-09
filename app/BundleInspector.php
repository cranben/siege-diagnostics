<?php

class BundleInspector
{
    private const MAX_JSON_BYTES = 5242880;

    public function inspect(string $zipPath): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZIP support is not available in this PHP installation.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            throw new RuntimeException('Could not open ZIP bundle.');
        }

        $result = [
            'collection_id' => null,
            'generated_at' => null,
            'collector_version' => null,
            'schema_version' => null,
            'manifest_json' => null,
            'collector_json' => null,
            'sections_json' => [],
            'sections' => [],
            'warnings' => [],
            'errors' => [],
        ];

        try {
            $manifest = $this->readJsonEntry($zip, 'manifest.json', $result);
            $collector = $this->readJsonEntry($zip, 'collector.json', $result);

            if (is_array($manifest)) {
                $result['manifest_json'] = $manifest;
                $result['collection_id'] = $this->stringValue($manifest, ['collection_id', 'id']);
                $result['generated_at'] = $this->stringValue($manifest, ['generated_at', 'created_at']);
                $result['collector_version'] = $this->stringValue($manifest, ['collector_version']);
                $result['schema_version'] = $this->stringValue($manifest, ['schema_version']);
            }

            if (is_array($collector)) {
                $result['collector_json'] = $collector;
                $result['collector_version'] = $result['collector_version']
                    ?? $this->stringValue($collector, ['collector_version', 'version']);
                $result['schema_version'] = $result['schema_version']
                    ?? $this->stringValue($collector, ['schema_version']);
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if (!is_string($name)) {
                    continue;
                }

                $normalizedName = $this->normalizeZipName($name);

                if ($normalizedName === null) {
                    $result['warnings'][] = "Skipped unsafe ZIP entry path: {$name}";
                    continue;
                }

                if (!$this->isAllowedSectionPath($normalizedName)) {
                    continue;
                }

                $section = $this->readJsonByIndex($zip, $i, $normalizedName, $result);

                if (!is_array($section)) {
                    continue;
                }

                $result['sections_json'][] = [
                    'file' => $normalizedName,
                    'data' => $section,
                ];

                $result['sections'][] = [
                    'file' => $normalizedName,
                    'name' => $this->sectionName($normalizedName, $section),
                    'status' => $this->stringValue($section, ['status']) ?? 'unknown',
                    'record_count' => $this->recordCount($section),
                    'warnings' => $this->listValue($section, ['warnings', 'warning']),
                    'errors' => $this->listValue($section, ['errors', 'error']),
                ];
            }
        } finally {
            $zip->close();
        }

        if (!$manifest && !$collector && count($result['sections']) === 0) {
            $result['errors'][] = 'No supported bundle JSON files found.';
        }

        return $result;
    }

    private function readJsonEntry(ZipArchive $zip, string $path, array &$result): ?array
    {
        $index = $zip->locateName($path);

        if ($index === false) {
            return null;
        }

        $name = $zip->getNameIndex($index);
        if (!is_string($name) || $this->normalizeZipName($name) !== $path) {
            return null;
        }

        return $this->readJsonByIndex($zip, $index, $path, $result);
    }

    private function readJsonByIndex(ZipArchive $zip, int $index, string $path, array &$result): ?array
    {
        $stat = $zip->statIndex($index);

        if (!$stat || ($stat['size'] ?? 0) > self::MAX_JSON_BYTES) {
            $result['errors'][] = "{$path} is too large to inspect.";
            return null;
        }

        $contents = $zip->getFromIndex($index);

        if (!is_string($contents)) {
            $result['errors'][] = "Could not read {$path}.";
            return null;
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            $result['errors'][] = "{$path} is not valid JSON.";
            return null;
        }

        return $decoded;
    }

    private function normalizeZipName(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);

        if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name)) {
            return null;
        }

        $parts = explode('/', $name);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return null;
            }
        }

        return implode('/', $parts);
    }

    private function isAllowedSectionPath(string $path): bool
    {
        return (bool)preg_match('/^sections\/[^\/]+\.json$/', $path);
    }

    private function sectionName(string $path, array $section): string
    {
        return $this->stringValue($section, ['section', 'section_name', 'name'])
            ?? basename($path, '.json');
    }

    private function recordCount(array $section): ?int
    {
        foreach (['record_count', 'records_count', 'count'] as $key) {
            if (isset($section[$key]) && is_numeric($section[$key])) {
                return (int)$section[$key];
            }
        }

        if (isset($section['records']) && is_array($section['records'])) {
            return count($section['records']);
        }

        return null;
    }

    private function stringValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
                return trim((string)$data[$key]);
            }
        }

        return null;
    }

    private function listValue(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                continue;
            }

            if (is_array($data[$key])) {
                return array_map('strval', $data[$key]);
            }

            if (trim((string)$data[$key]) !== '') {
                return [(string)$data[$key]];
            }
        }

        return [];
    }
}
