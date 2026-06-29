<?php

function bundle_decode_json_field($value): array
{
    if (!is_string($value) || $value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function bundle_string_value(array $data, array $keys): ?string
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && trim((string)$data[$key]) !== '') {
            return trim((string)$data[$key]);
        }
    }

    return null;
}

function bundle_list_value(array $data, array $keys): array
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

function bundle_record_count(array $section): ?int
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

function bundle_normalize_section_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\.json$/', '', $value);
    $value = str_replace('\\', '/', $value);

    if (str_contains($value, '/')) {
        $value = basename($value);
    }

    return preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
}

function bundle_section_name(array $sectionEntry): string
{
    $sectionFile = $sectionEntry['file'] ?? '';
    $sectionData = isset($sectionEntry['data']) && is_array($sectionEntry['data'])
        ? $sectionEntry['data']
        : [];

    return bundle_string_value($sectionData, ['section', 'section_name', 'name'])
        ?? basename((string)$sectionFile, '.json');
}

function bundle_section_data(array $sectionEntry): array
{
    return isset($sectionEntry['data']) && is_array($sectionEntry['data'])
        ? $sectionEntry['data']
        : [];
}

function bundle_find_section(array $sectionsJson, string $expectedKey): ?array
{
    $expectedKey = bundle_normalize_section_key($expectedKey);

    foreach ($sectionsJson as $sectionEntry) {
        if (!is_array($sectionEntry)) {
            continue;
        }

        $sectionFile = isset($sectionEntry['file']) ? bundle_normalize_section_key((string)$sectionEntry['file']) : '';
        $sectionName = bundle_normalize_section_key(bundle_section_name($sectionEntry));

        if ($sectionFile === $expectedKey || $sectionName === $expectedKey) {
            return bundle_section_data($sectionEntry);
        }
    }

    return null;
}

function bundle_count_like($value): ?int
{
    if (!is_array($value)) {
        return null;
    }

    $directCount = bundle_record_count($value);
    if ($directCount !== null) {
        return $directCount;
    }

    foreach (['calls', 'records', 'items', 'entries', 'results', 'selected_calls', 'selected'] as $key) {
        if (isset($value[$key]) && is_array($value[$key])) {
            return count($value[$key]);
        }
    }

    if (bundle_is_list($value)) {
        return count($value);
    }

    return null;
}

function bundle_is_list(array $value): bool
{
    $expectedKey = 0;

    foreach ($value as $key => $_) {
        if ($key !== $expectedKey) {
            return false;
        }

        $expectedKey++;
    }

    return true;
}

function bundle_nested_value(array $data, array $path)
{
    $current = $data;

    foreach ($path as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return null;
        }

        $current = $current[$segment];
    }

    return $current;
}

function bundle_call_value(array $call, array $paths)
{
    foreach ($paths as $path) {
        $value = bundle_nested_value($call, $path);

        if ($value === null) {
            continue;
        }

        if (is_string($value) && trim($value) === '') {
            continue;
        }

        if (is_array($value) && count($value) === 0) {
            continue;
        }

        return $value;
    }

    return null;
}

function bundle_stringify_value($value): ?string
{
    if ($value === null) {
        return null;
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_scalar($value)) {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    if (is_array($value)) {
        $parts = [];

        foreach ($value as $item) {
            if (is_scalar($item) || is_bool($item)) {
                $string = bundle_stringify_value($item);
                if ($string !== null) {
                    $parts[] = $string;
                }
            }
        }

        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    return null;
}

function bundle_call_display_value(array $call, array $paths): ?string
{
    return bundle_stringify_value(bundle_call_value($call, $paths));
}

function bundle_exclusion_status(array $policy, array $paths): ?string
{
    $value = bundle_call_value($policy, $paths);

    if ($value === null) {
        return null;
    }

    if (is_bool($value)) {
        return $value ? 'Excluded' : 'Included';
    }

    if (is_numeric($value)) {
        return ((int)$value) !== 0 ? 'Excluded' : 'Included';
    }

    $normalized = strtolower(trim((string)$value));

    if ($normalized === '') {
        return null;
    }

    $truthy = ['1', 'true', 'yes', 'y', 'excluded', 'exclude', 'omitted', 'omit', 'removed', 'redacted'];
    $falsey = ['0', 'false', 'no', 'n', 'included', 'include', 'retained', 'kept', 'present'];

    if (in_array($normalized, $truthy, true)) {
        return 'Excluded';
    }

    if (in_array($normalized, $falsey, true)) {
        return 'Included';
    }

    return ucfirst($normalized);
}

function bundle_exclusion_status_from_paths(array $policy, array $excludePaths, array $includePaths = []): ?string
{
    $excludeStatus = bundle_exclusion_status($policy, $excludePaths);

    if ($excludeStatus !== null) {
        return $excludeStatus;
    }

    if (count($includePaths) === 0) {
        return null;
    }

    $includeValue = bundle_call_value($policy, $includePaths);

    if ($includeValue === null) {
        return null;
    }

    if (is_bool($includeValue)) {
        return $includeValue ? 'Included' : 'Excluded';
    }

    if (is_numeric($includeValue)) {
        return ((int)$includeValue) !== 0 ? 'Included' : 'Excluded';
    }

    $normalized = strtolower(trim((string)$includeValue));

    if ($normalized === '') {
        return null;
    }

    $truthy = ['1', 'true', 'yes', 'y', 'included', 'include', 'retained', 'kept', 'present'];
    $falsey = ['0', 'false', 'no', 'n', 'excluded', 'exclude', 'omitted', 'omit', 'removed', 'redacted'];

    if (in_array($normalized, $truthy, true)) {
        return 'Included';
    }

    if (in_array($normalized, $falsey, true)) {
        return 'Excluded';
    }

    return ucfirst($normalized);
}

function bundle_selected_calls(array $selectedCallsSection): array
{
    foreach (['calls', 'selected_calls', 'records', 'items', 'entries'] as $key) {
        if (isset($selectedCallsSection[$key]) && is_array($selectedCallsSection[$key])) {
            return array_values($selectedCallsSection[$key]);
        }
    }

    return [];
}

function bundle_safe_call(array $calls, int $index): ?array
{
    if ($index < 0 || !array_key_exists($index, $calls) || !is_array($calls[$index])) {
        return null;
    }

    return $calls[$index];
}
