<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Services\System\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct(
        private readonly HolidayService $holidays,
    ) {
    }

    // ─── GetByYear ─────────────────────────────────────────────────────
    public function getByYear(int $year): JsonResponse
    {
        if ($year < 1900 || $year > 2100) {
            return response()->json([
                'error' => 'Invalid year. Please provide a year between 1900 and 2100.',
            ], 400);
        }

        try {
            $holidays = $this->holidays->forYear($year);

            return response()->json([
                'success'  => true,
                'year'     => $year,
                'count'    => count($holidays),
                'holidays' => $holidays,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to fetch holidays',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─── Current ─────────────────────────────────────────────────────
    public function current(): JsonResponse
    {
        return $this->getByYear(now()->year);
    }

    // ─── GetRange ─────────────────────────────────────────────────────
    public function getRange(int $startYear, int $endYear): JsonResponse
    {
        if ($startYear > $endYear) {
            return response()->json(['error' => 'Start year cannot be greater than end year'], 400);
        }

        if ($endYear - $startYear > 10) {
            return response()->json(['error' => 'Range cannot exceed 10 years'], 400);
        }

        try {
            return response()->json([
                'success'    => true,
                'start_year' => $startYear,
                'end_year'   => $endYear,
                'holidays'   => $this->holidays->forRange($startYear, $endYear),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to fetch holidays',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─── CheckDate ─────────────────────────────────────────────────────
    public function checkDate(Request $request): JsonResponse
    {
        $date = $request->query('date');

        if (! $date) {
            return response()->json(['error' => 'Date parameter is required (format: YYYY-MM-DD)'], 400);
        }

        try {
            $holiday = $this->holidays->findOnDate($date);

            return response()->json([
                'success'    => true,
                'date'       => \Carbon\Carbon::parse($date)->toDateString(),
                'is_holiday' => $holiday !== null,
                'holiday'    => $holiday,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format. Use YYYY-MM-DD'], 400);
        }
    }

    // ─── Next ─────────────────────────────────────────────────────
    public function next(): JsonResponse
    {
        try {
            return response()->json([
                'success'      => true,
                'next_holiday' => $this->holidays->next(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to fetch next holiday',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─── Search ─────────────────────────────────────────────────────
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $year  = $request->query('year', now()->year);

        if (! $query) {
            return response()->json(['error' => 'Search query (q) parameter is required'], 400);
        }

        try {
            $results = $this->holidays->search($query, (int) $year);

            return response()->json([
                'success' => true,
                'query'   => $query,
                'year'    => $year,
                'count'   => count($results),
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Search failed',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // ─── ClearCache ─────────────────────────────────────────────────────
    public function clearCache(?int $year = null): JsonResponse
    {
        if ($year) {
            $this->holidays->clearCacheForYear($year);

            return response()->json(['success' => true, 'message' => "Cache cleared for year {$year}"]);
        }

        $this->holidays->clearAllCache();

        return response()->json(['success' => true, 'message' => 'All holiday caches cleared']);
    }
}
