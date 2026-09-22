<?php

namespace HoangPhamDev\SimpleAdminGenerator\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrudSchemaInspector
{
    /** @return array<int, array<string, mixed>> */
    public function inspect(string $table): array
    {
        $columns = $this->mysqlColumns($table) ?? $this->genericColumns($table);

        return array_values(array_filter(array_map(function (array $column): ?array {
            $name = $column['name'];
            $type = strtolower($column['type']);

            if ($column['auto_increment'] || in_array($name, ['created_at', 'updated_at', 'deleted_at'], true)
                || str_contains($type, 'blob') || str_contains($type, 'binary')) {
                return null;
            }

            $kind = $this->kind($name, $type);
            if ($kind === 'unsupported') {
                return null;
            }

            return [
                'name' => $name,
                'label' => ucfirst(str_replace('_', ' ', $name)),
                'type' => $type,
                'kind' => $kind,
                'nullable' => $column['nullable'],
                'length' => $this->length($type),
                'options' => $this->options($type),
            ];
        }, $columns)));
    }

    /** @return array<int, array<string, mixed>>|null */
    private function mysqlColumns(string $table): ?array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return null;
        }

        $quoted = str_replace('`', '``', $table);
        $rows = DB::select("SHOW FULL COLUMNS FROM `{$quoted}`");

        return array_map(static fn ($row) => [
            'name' => $row->Field,
            'type' => $row->Type,
            'nullable' => $row->Null === 'YES',
            'auto_increment' => str_contains(strtolower((string) $row->Extra), 'auto_increment'),
        ], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function genericColumns(string $table): array
    {
        return array_map(static fn (string $name) => [
            'name' => $name,
            'type' => Schema::getColumnType($table, $name),
            'nullable' => true,
            'auto_increment' => $name === 'id',
        ], Schema::getColumnListing($table));
    }

    private function kind(string $name, string $type): string
    {
        if (str_starts_with($type, 'enum(')) return 'enum';
        if (str_starts_with($type, 'set(')) return 'set';
        if (preg_match('/^(tinyint\(1\)|boolean|bool)/', $type)) return 'boolean';
        if (preg_match('/^(bigint|int|integer|smallint|mediumint|tinyint)/', $type)) return 'integer';
        if (preg_match('/^(decimal|numeric|float|double|real)/', $type)) return 'decimal';
        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) return 'datetime';
        if (str_starts_with($type, 'date')) return 'date';
        if (str_starts_with($type, 'time')) return 'time';
        if (str_starts_with($type, 'json')) return 'json';
        if (preg_match('/(text|clob)/', $type)) return 'textarea';
        if (preg_match('/(char|varchar|string)/', $type)) return str_ends_with($name, 'email') || $name === 'email' ? 'email' : 'text';

        return 'unsupported';
    }

    private function length(string $type): ?int
    {
        return preg_match('/^(?:var)?char\((\d+)\)/', $type, $matches) ? (int) $matches[1] : null;
    }

    /** @return array<int, string> */
    private function options(string $type): array
    {
        if (!preg_match('/^(?:enum|set)\((.*)\)$/', $type, $matches)) return [];

        return str_getcsv($matches[1], ',', "'");
    }
}
