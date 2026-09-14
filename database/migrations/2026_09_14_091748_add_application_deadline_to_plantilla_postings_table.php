<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plantilla_postings', function (Blueprint $table) {
            $table->date('application_deadline')->nullable()->after('date_posted');
        });

        DB::table('plantilla_postings')->orderBy('id')->chunkById(500, function ($postings) {
            foreach ($postings as $posting) {
                $datePosted = $posting->date_posted;
                if (! $datePosted) {
                    continue;
                }

                $deadline = date('Y-m-d', strtotime($datePosted . ' +15 days'));

                if ($posting->closing_date && $posting->closing_date < $deadline) {
                    $deadline = $posting->closing_date;
                }

                DB::table('plantilla_postings')
                    ->where('id', $posting->id)
                    ->update(['application_deadline' => $deadline]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_postings', function (Blueprint $table) {
            $table->dropColumn('application_deadline');
        });
    }
};