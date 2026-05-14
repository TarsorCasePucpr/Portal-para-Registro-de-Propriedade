<?php
declare(strict_types=1);

function rowMatchesTerm(array $row, string $term, array $fields): bool
{
    $needle = mb_strtolower(trim($term));
    if ($needle === '') return true;

    foreach ($fields as $f) {
        if (!isset($row[$f])) continue;
        $hay = mb_strtolower((string) $row[$f]);
        if ($hay !== '' && mb_strpos($hay, $needle) !== false) return true;
    }
    return false;
}

function paginateArray(array $rows, int $page, int $perPage): array
{
    $total     = count($rows);
    $lastPage  = $total > 0 ? (int) ceil($total / $perPage) : 1;
    $offset    = max(0, ($page - 1) * $perPage);
    $slice     = array_slice($rows, $offset, $perPage);

    return [
        'items'     => array_values($slice),
        'total'     => $total,
        'last_page' => $lastPage,
    ];
}
