<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class PdsExtractController extends Controller
{
    private const CLAUDE_MODEL = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS   = 4096;

    /** Sheet names required in the CSC PDS 2025 Excel template. */
    private const REQUIRED_SHEETS = ['C1', 'C2', 'C3', 'C4'];

    // ── Dropdown option maps — must match exactly what the frontend dropdowns expect ──

    private const CIVIL_STATUS_MAP = [
        'WIDOW-ER'    => 'WIDOW/ER',
        'WIDOWER'     => 'WIDOW/ER',
        'WIDOW'       => 'WIDOW/ER',
        'SOLO-PARENT' => 'SOLO PARENT',
    ];

    private const GOVT_ID_MAP = [
        'GSIS'            => 'GSIS ID',
        'SSS'             => 'SSS ID',
        'PRC'             => 'PRC ID',
        'DRIVER LICENSE'  => "DRIVER'S LICENSE",
        'DRIVERS LICENSE' => "DRIVER'S LICENSE",
        'VOTER ID'        => "VOTER'S ID",
        'VOTERS ID'       => "VOTER'S ID",
        'PHILSYS'         => 'PHILSYS ID',
        'POSTAL'          => 'POSTAL ID',
    ];

    private const SEX_MAP = [
        'M' => 'MALE',
        'F' => 'FEMALE',
    ];

    private const YES_NO_KEYS = [
        'related_3rd_degree', 'related_4th_degree',
        'found_guilty_admin', 'criminally_charged',
        'convicted_crime', 'separated_from_service',
        'candidate_in_election', 'resigned_to_campaign',
        'immigrant_status', 'is_indigenous', 'is_pwd', 'is_solo_parent',
    ];

    // Date keys and email — must not be uppercased
    private const PRESERVE_CASE_KEYS = [
        'date_of_birth', 'license_date_issued', 'license_expiry_date',
        'exam_date', 'valid_until', 'date_from', 'date_to', 'from', 'to',
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a Philippine government PDS (Personal Data Sheet, CS Form No. 212) parser.
The user will provide a PDS document as Excel data. Extract ALL available fields and return ONLY a valid JSON object — no markdown, no explanation, no backticks.

Use EXACTLY these field keys (snake_case). Output only the fields you can find; omit fields with no data.

SCALAR FIELDS:
surname, first_name, middle_name, name_extension,
date_of_birth (YYYY-MM-DD), place_of_birth, sex_at_birth (MALE or FEMALE),
civil_status (SINGLE/MARRIED/WIDOW/ER/SEPARATED/SOLO PARENT/OTHERS),
height (decimal metres e.g. 1.65), weight (decimal kg e.g. 65.0),
blood_type (A+/A-/B+/B-/AB+/AB-/O+/O-),
citizenship,
umid_id, pagibig_id, philhealth_no, philsys_number, tin_no, agency_employee_no,
residential_house_no, residential_street, residential_subdivision,
residential_barangay, residential_city, residential_province, residential_zip,
permanent_house_no, permanent_street, permanent_subdivision,
permanent_barangay, permanent_city, permanent_province, permanent_zip,
telephone_no, mobile_no, email_address,
spouse_surname, spouse_first_name, spouse_middle_name, spouse_extension,
spouse_occupation, spouse_employer, spouse_business_address, spouse_telephone,
father_surname, father_first_name, father_middle_name, father_extension,
mother_maiden_surname, mother_first_name, mother_middle_name,
related_3rd_degree (YES/NO), related_3rd_degree_details,
related_4th_degree (YES/NO), related_4th_degree_details,
found_guilty_admin (YES/NO), found_guilty_admin_details,
criminally_charged (YES/NO), criminally_charged_details,
convicted_crime (YES/NO), convicted_crime_details,
separated_from_service (YES/NO), separated_from_service_details,
candidate_in_election (YES/NO), candidate_in_election_details,
resigned_to_campaign (YES/NO), resigned_to_campaign_details,
immigrant_status (YES/NO), immigrant_country,
is_indigenous (YES/NO), indigenous_specify,
is_pwd (YES/NO), pwd_id_no,
is_solo_parent (YES/NO), solo_parent_id_no,
govt_id_type (PASSPORT/GSIS ID/SSS ID/PRC ID/DRIVER'S LICENSE/UMID/VOTER'S ID/PHILSYS ID/POSTAL ID/OTHER),
govt_id_no, govt_id_date_place,
license_number, license_date_issued (YYYY-MM-DD), license_expiry_date (YYYY-MM-DD), license_place_issued

ARRAY FIELDS (return as JSON arrays):
children: [{name, date_of_birth}]
edu_elementary / edu_secondary / edu_vocational / edu_college / edu_graduate:
  [{school, degree, period_from, period_to, highest_level, year_graduated, honors}]
eligibilities: [{eligibility, rating, exam_date, place, license_no, valid_until}]
work_experiences: [{from, to, position, department, monthly_salary, salary_grade, status, govt_service (Y or N)}]
voluntary_works: [{org_name_address, from, to, hours, position}]
trainings: [{title, from, to, hours, type, sponsored_by}]
special_skills: [{skill}]
non_academic_distinctions: [{distinction}]
organization_memberships: [{organization}]
references: [{name, address, contact}]

IMPORTANT RULES:
- All string values must be UPPERCASE except email_address and date fields.
- civil_status must be exactly one of: SINGLE, MARRIED, WIDOW/ER, SEPARATED, SOLO PARENT, OTHERS
- govt_id_type must be exactly one of: PASSPORT, GSIS ID, SSS ID, PRC ID, DRIVER'S LICENSE, UMID, VOTER'S ID, PHILSYS ID, POSTAL ID, OTHER
- sex_at_birth must be exactly MALE or FEMALE
- YES/NO fields must be exactly YES or NO
- work_experiences.govt_service must be exactly Y or N
- Return ONLY the JSON object. No prose, no markdown fences.
PROMPT;

    // ─────────────────────────────────────────────────────────────────────────
    // EXTRACT ENDPOINT
    // ─────────────────────────────────────────────────────────────────────────

    public function extract(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        // ── Only Excel files are accepted ─────────────────────────────────────
        if (! in_array($ext, ['xlsx', 'xls'])) {
            return response()->json([
                'message' =>
                    "Tanggap lamang ang Excel file (.xlsx o .xls). Gamitin ang opisyal na CSC Form 212 (Revised 2025) Excel template.\n\n" .
                    'Only Excel files are accepted (.xlsx or .xls). Please use the official CSC Form 212 (Revised 2025) Excel template.',
            ], 422);
        }

        // ── Validate template (xlsx only — xls is legacy binary, skip check) ─
        if ($ext === 'xlsx') {
            $check = $this->validateXlsxTemplate($file->getRealPath());
            if (! $check['valid']) {
                return response()->json(['message' => $check['message']], 422);
            }
        }

        // ── Send to Claude ────────────────────────────────────────────────────
        $messages = $this->buildExcelMessages($file, $ext);

        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model'      => self::CLAUDE_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => self::SYSTEM_PROMPT,
            'messages'   => $messages,
        ]);

        if ($response->failed()) {
            $body = $response->json();
            return response()->json([
                'message' => $body['error']['message'] ?? 'Claude API error.',
            ], $response->status());
        }

        $rawText = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        // Strip accidental markdown fences
        $clean = trim(preg_replace(
            ['/^```(?:json)?\s*/i', '/```\s*$/i'],
            '',
            $rawText
        ));

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'message' => 'Hindi ma-parse ang nakuhang data. Subukan ulit.' . "\n\n" .
                    'Could not parse extracted data. Please try again.',
            ], 422);
        }

        // ── Normalise all values (uppercase + dropdown mapping) ───────────────
        $parsed = $this->normaliseExtracted($parsed);

        return response()->json(['data' => $parsed]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NORMALISE EXTRACTED DATA
    // Uppercases all string fields and maps dropdown values to exact frontend
    // option strings so Select components receive matching values.
    // ─────────────────────────────────────────────────────────────────────────

    private function normaliseExtracted(array $data): array
    {
        $normaliseRow = fn (array $row) => collect($row)
            ->map(fn ($v, $k) => is_string($v) ? $this->normaliseValue($k, $v) : $v)
            ->toArray();

        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Array of row objects (children, work_experiences, trainings, etc.)
                $result[$key] = array_map($normaliseRow, $value);
            } else {
                $result[$key] = is_string($value)
                    ? $this->normaliseValue($key, $value)
                    : $value;
            }
        }

        return $result;
    }

    /**
     * Normalise a single scalar value for a given field key.
     * Rules (in priority order):
     *  1. Date fields         → return as-is (YYYY-MM-DD)
     *  2. email_address       → lowercase
     *  3. civil_status        → map to exact dropdown option
     *  4. govt_id_type        → map to exact dropdown option
     *  5. sex_at_birth        → map M/F shorthand
     *  6. YES/NO fields       → normalise Y/N/TRUE/FALSE → YES/NO
     *  7. work govt_service   → normalise to Y or N
     *  8. everything else     → strtoupper + trim
     */
    private function normaliseValue(string $key, string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') return '';

        // 1. Date fields — keep exactly as-is
        if (in_array($key, self::PRESERVE_CASE_KEYS)) {
            return $trimmed;
        }

        // 2. Email — lowercase
        if ($key === 'email_address') {
            return strtolower($trimmed);
        }

        $upper = strtoupper($trimmed);

        // 3. civil_status → map WIDOW-ER etc. to WIDOW/ER
        if ($key === 'civil_status') {
            return self::CIVIL_STATUS_MAP[$upper] ?? $upper;
        }

        // 4. govt_id_type → map GSIS → GSIS ID etc.
        if ($key === 'govt_id_type') {
            return self::GOVT_ID_MAP[$upper] ?? $upper;
        }

        // 5. sex_at_birth → map M/F shorthand
        if ($key === 'sex_at_birth') {
            return self::SEX_MAP[$upper] ?? $upper;
        }

        // 6. YES/NO question fields
        if (in_array($key, self::YES_NO_KEYS)) {
            if (in_array($upper, ['Y', 'YES', 'TRUE', '1'])) return 'YES';
            if (in_array($upper, ['N', 'NO', 'FALSE', '0'])) return 'NO';
            return $upper;
        }

        // 7. Work govt_service → Y or N only
        if ($key === 'govt_service') {
            if (in_array($upper, ['Y', 'YES', 'TRUE', '1'])) return 'Y';
            if (in_array($upper, ['N', 'NO', 'FALSE', '0'])) return 'N';
            return $upper;
        }

        // 8. Default — uppercase
        return $upper;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPPERCASE PAYLOAD HELPER
    // Use this in any controller that saves PDS form data to the database
    // to guarantee all stored strings are uppercase.
    //
    // Usage:
    //   $payload = $this->uppercasePayload($request->except(['avatar', '_method']));
    //   $employeeInfo->updateOrCreate(['employee_id' => $employee->id], $payload);
    // ─────────────────────────────────────────────────────────────────────────

    public static function uppercasePayload(array $data): array
    {
        $skipKeys = [
            'email_address',
            'date_of_birth',
            'license_date_issued',
            'license_expiry_date',
            'govt_id_date_place',
        ];

        return collect($data)->map(function ($value, $key) use ($skipKeys) {
            if (! is_string($value) || $value === '') return $value;
            if ($key === 'email_address')            return strtolower(trim($value));
            if (in_array($key, $skipKeys))           return $value;
            return strtoupper(trim($value));
        })->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEMPLATE VALIDATION
    // An .xlsx file is a ZIP archive of XML files (OOXML spec). We inspect:
    //   xl/workbook.xml       → declared sheet names
    //   xl/sharedStrings.xml  → shared string table (where "Revised 2025" lives)
    //   xl/worksheets/sheet1.xml → fallback for inline string values
    // ─────────────────────────────────────────────────────────────────────────

    private function validateXlsxTemplate(string $filePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            return [
                'valid'   => false,
                'message' =>
                    "Hindi mabasa ang Excel file. Siguraduhing hindi sira ang file at subukan ulit.\n\n" .
                    'Cannot read the Excel file. Please make sure the file is not corrupted and try again.',
            ];
        }

        // ── 1. Parse workbook.xml for sheet names ─────────────────────────────
        $workbookXml = $zip->getFromName('xl/workbook.xml');

        if ($workbookXml === false) {
            $zip->close();
            return [
                'valid'   => false,
                'message' =>
                    "Hindi ito isang valid na Excel (.xlsx) file.\n\n" .
                    'This is not a valid Excel (.xlsx) file.',
            ];
        }

        preg_match_all('/<sheet\s[^>]*\bname=["\']([^"\']+)["\'][^>]*\/?>/i', $workbookXml, $m);
        $sheetNames = $m[1] ?? [];

        $missing = array_diff(self::REQUIRED_SHEETS, $sheetNames);
        if (! empty($missing)) {
            $zip->close();
            return [
                'valid'   => false,
                'message' =>
                    "Hindi ito ang opisyal na CSC PDS Excel template. " .
                    "Tinatanggap lamang ang CSC Form 212 (Revised 2025) na may mga sheet na C1, C2, C3, at C4. " .
                    "I-download ang tamang template sa csc.gov.ph at i-upload ulit, o punan ang form nang manual dito sa system.\n\n" .
                    'This is not the official CSC PDS Excel template. ' .
                    'Only CSC Form 212 (Revised 2025) with sheets C1, C2, C3, and C4 is accepted. ' .
                    'Please download the correct template from csc.gov.ph and re-upload, or fill in the form manually.',
            ];
        }

        // ── 2. Look for "2025" in the shared string table ─────────────────────
        $found2025 = false;

        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStrings !== false && str_contains($sharedStrings, '2025')) {
            $found2025 = true;
        }

        if (! $found2025) {
            $sheet1 = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet1 !== false && str_contains($sheet1, '2025')) {
                $found2025 = true;
            }
        }

        $zip->close();

        if (! $found2025) {
            return [
                'valid'   => false,
                'message' =>
                    "Mali ang bersyon ng PDS template. " .
                    "Ang tinatanggap lamang ay ang CSC Form 212 (Revised 2025). " .
                    "I-download ang pinakabagong template sa csc.gov.ph, punan ito ng iyong impormasyon, at i-upload ulit. " .
                    "O kaya naman, maaari kang mag-fill up ng form nang manual dito sa system.\n\n" .
                    'Wrong PDS template version. Only CSC Form 212 (Revised 2025) is accepted. ' .
                    'Please download the latest template from csc.gov.ph, fill it out, and re-upload. ' .
                    'You may also fill in the form manually here in the system.',
            ];
        }

        return ['valid' => true];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BUILD CLAUDE MESSAGE PAYLOAD
    // ─────────────────────────────────────────────────────────────────────────

    private function buildExcelMessages($file, string $ext): array
    {
        $mimeType = $ext === 'xls'
            ? 'application/vnd.ms-excel'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        return [[
            'role'    => 'user',
            'content' => [
                [
                    'type'   => 'document',
                    'source' => [
                        'type'       => 'base64',
                        'media_type' => $mimeType,
                        'data'       => $base64,
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => 'This is a CSC Form 212 (Revised 2025) PDS Excel file. Extract all fields and return the JSON as instructed.',
                ],
            ],
        ]];
    }
}