<?php

namespace App\Services\Dtr;

use App\Exceptions\Dtr\DtrException;
use App\Models\Dtr\DtrAttendance;
use App\Models\Dtr\DtrFace;
use Illuminate\Support\Facades\DB;

class LegacyDtrImporter
{
    private array $byNumber = [];

    private array $byName = [];

    private array $stats = [];

    public function __construct(private readonly ImageStore $images)
    {
    }

    public function import(string $sourceDb, bool $dryRun = false): array
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $sourceDb)) {
            throw new DtrException('Invalid source database name.', 422);
        }

        $this->stats = ['faces_imported' => 0, 'faces_quarantined' => 0, 'attendances_imported' => 0, 'attendances_quarantined' => 0, 'already_imported' => 0];
        $this->byNumber = DB::table('employees')->whereNotNull('employee_number')->get(['id', 'employee_number'])
            ->mapWithKeys(fn ($e) => [$this->norm($e->employee_number) => (int) $e->id])->all();
        $this->byName = [];

        $this->importFaces($sourceDb, $dryRun);
        $this->importAttendances($sourceDb, $dryRun);

        return $this->stats;
    }

    private function importFaces(string $db, bool $dry): void
    {
        DB::table("$db.faces")->orderBy('id')->chunkById(50, function ($rows) use ($dry) {
            foreach ($rows as $row) {
                $employeeId = $this->byNumber[$this->norm($row->employee_number ?? '')] ?? null;

                if (! $employeeId) {
                    $this->quarantine('faces', $row, 'no_employee_match', $dry);
                    continue;
                }
                $this->rememberName($row->name ?? '', $employeeId);

                $encodings = $this->decodeEncodings($row);
                if ($encodings === null) {
                    $this->quarantine('faces', $row, 'invalid_encoding', $dry);
                    continue;
                }
                if ($dry) {
                    $this->stats['faces_imported']++;
                    continue;
                }

                $path = $this->safeStore($row->image ?? null, 'faces');
                $prev = DtrFace::where('employee_id', $employeeId)->first();
                DtrFace::updateOrCreate(['employee_id' => $employeeId], [
                    'encodings' => $encodings, 'image_path' => $path, 'legacy_id' => $row->id,
                ]);
                $this->images->delete($prev?->image_path);
                DB::table('dtr_legacy_quarantine')->where(['source_table' => 'faces', 'legacy_id' => $row->id])->delete();
                $this->stats['faces_imported']++;
            }
        });
    }

    private function importAttendances(string $db, bool $dry): void
    {
        DB::table("$db.attendances")->orderBy('id')->chunkById(200, function ($rows) use ($dry) {
            foreach ($rows as $row) {
                if (! $dry && DtrAttendance::where('legacy_id', $row->id)->exists()) {
                    $this->stats['already_imported']++;
                    continue;
                }

                $employeeId = $this->byNumber[$this->norm($row->employee_number ?? '')]
                    ?? ($this->byName[$this->norm($row->name ?? '')] ?? null);

                if (! $employeeId || ! $row->time_in) {
                    $this->quarantine('attendances', $row, $employeeId ? 'missing_time_in' : 'no_employee_match', $dry);
                    continue;
                }
                if ($dry) {
                    $this->stats['attendances_imported']++;
                    continue;
                }

                DtrAttendance::create([
                    'employee_id'         => $employeeId,
                    'work_date'           => $row->date,
                    'time_in'             => $row->time_in,
                    'time_in_image_path'  => $this->safeStore($row->time_in_image ?? $row->image ?? null, 'attendance'),
                    'time_in_source'      => 'legacy',
                    'time_out'            => $row->time_out,
                    'time_out_date'       => $row->time_out ? ($row->time_out_date ?? $row->date) : null,
                    'time_out_image_path' => $row->time_out ? $this->safeStore($row->time_out_image ?? null, 'attendance') : null,
                    'time_out_source'     => $row->time_out ? 'legacy' : null,
                    'legacy_id'           => $row->id,
                ]);
                DB::table('dtr_legacy_quarantine')->where(['source_table' => 'attendances', 'legacy_id' => $row->id])->delete();
                $this->stats['attendances_imported']++;
            }
        });
    }

    private function decodeEncodings(object $row): ?array
    {
        $list = ! empty($row->encodings) ? json_decode($row->encodings, true) : null;
        if (! is_array($list) || $list === []) {
            $single = ! empty($row->encoding) ? json_decode($row->encoding, true) : null;
            $list   = is_array($single) && $single !== [] ? [$single] : null;
        }
        if ($list === null) {
            return null;
        }
        foreach ($list as $vector) {
            if (! is_array($vector) || count($vector) !== 128) {
                return null; 
            }
        }

        return $list;
    }

    private function safeStore(?string $dataUrl, string $subdir): ?string
    {
        if (! $dataUrl) {
            return null;
        }
        try {
            return $this->images->putDataUrl($dataUrl, $subdir);
        } catch (DtrException) {
            return null; 
        }
    }

    private function quarantine(string $table, object $row, string $reason, bool $dry): void
    {
        $this->stats["{$table}_quarantined"]++;
        if ($dry) {
            return;
        }

        DB::table('dtr_legacy_quarantine')->updateOrInsert(
            ['source_table' => $table, 'legacy_id' => $row->id],
            [
                'employee_number' => $row->employee_number ?? null,
                'name'            => $row->name ?? null,
                'reason'          => $reason,
                'payload'         => json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        );
    }

    private function rememberName(string $name, int $employeeId): void
    {
        $key = $this->norm($name);
        if ($key === '') {
            return;
        }
        $this->byName[$key] = (array_key_exists($key, $this->byName) && $this->byName[$key] !== $employeeId) ? null : $employeeId;
    }

    private function norm(?string $v): string
    {
        return mb_strtoupper(trim((string) $v));
    }
}
