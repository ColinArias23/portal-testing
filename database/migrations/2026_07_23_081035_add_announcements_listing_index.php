<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing(
            'announcements',
            ['is_archived', 'is_pinned', 'created_at'],
            'announcements_archived_pinned_created_index'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('announcements', 'announcements_archived_pinned_created_index');
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            return ! empty(DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]));
        }

        if ($driver === 'pgsql') {
            return ! empty(DB::select(
                'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            ));
        }

        if ($driver === 'sqlite') {
            $result = DB::select("PRAGMA index_list({$table})");
            return collect($result)->contains(fn ($row) => $row->name === $indexName);
        }

        return false;
    }
};
