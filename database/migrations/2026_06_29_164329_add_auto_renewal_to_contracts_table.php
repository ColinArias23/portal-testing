<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // ── Contract period ───────────────────────────────────────────────
            // start_date already exists as datetime — we add end_date beside it.
            $table->date('end_date')->nullable()->after('start_date');

            // ── Auto-renewal configuration ────────────────────────────────────
            // Placed after file_path to keep existing column order intact.
            $table->boolean('auto_renewal_enabled')
                  ->default(false)
                  ->after('file_path');

            $table->unsignedTinyInteger('auto_renewal_duration_months')
                  ->default(6)
                  ->comment('Fixed at 6 months per LGU policy')
                  ->after('auto_renewal_enabled');

            // NULL = triggers in both June AND December windows
            $table->enum('auto_renewal_month', ['JUNE', 'DECEMBER'])
                  ->nullable()
                  ->comment('NULL means both June 1 and December 1')
                  ->after('auto_renewal_duration_months');

            // ── Audit / chain tracking ────────────────────────────────────────
            $table->timestamp('last_auto_renewed_at')
                  ->nullable()
                  ->after('auto_renewal_month');

            $table->unsignedBigInteger('parent_contract_id')
                  ->nullable()
                  ->comment('FK to the contract this was renewed from')
                  ->after('last_auto_renewed_at');

            $table->unsignedInteger('renewal_generation')
                  ->default(0)
                  ->comment('0 = original, 1 = first renewal, 2 = second, etc.')
                  ->after('parent_contract_id');

            // ── Foreign key ───────────────────────────────────────────────────
            $table->foreign('parent_contract_id')
                  ->references('id')
                  ->on('contracts')
                  ->nullOnDelete();

            // ── Composite index used by the scheduler ─────────────────────────
            $table->index(
                ['auto_renewal_enabled', 'end_date'],
                'idx_contracts_auto_renewal'
            );
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['parent_contract_id']);
            $table->dropIndex('idx_contracts_auto_renewal');
            $table->dropColumn([
                'end_date',
                'auto_renewal_enabled',
                'auto_renewal_duration_months',
                'auto_renewal_month',
                'last_auto_renewed_at',
                'parent_contract_id',
                'renewal_generation',
            ]);
        });
    }
};