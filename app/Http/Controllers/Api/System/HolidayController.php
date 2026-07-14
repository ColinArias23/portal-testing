<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class HolidayController extends Controller
{
    private const API_URL = 'https://date.nager.at/api/v3/publicholidays';
    private const COUNTRY_CODE = 'PH';
    private const CACHE_DURATION = 24 * 60; 
// ─── GetByYear ─────────────────────────────────────────────────────
    public function getByYear(int $year): JsonResponse
    {
        if ($year < 1900 || $year > 2100) {
            return response()->json([
                'error' => 'Invalid year. Please provide a year between 1900 and 2100.'
            ], 400);
        }

        try {
            $cacheKey = "ph_holidays_{$year}";

            $holidays = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($year) {
                return $this->fetchHolidaysFromApi($year);
            });

            return response()->json([
                'success' => true,
                'year' => $year,
                'count' => count($holidays),
                'holidays' => $holidays
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch holidays',
                'message' => config('app.debug') ? $e->getMessage() : null
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
            return response()->json([
                'error' => 'Start year cannot be greater than end year'
            ], 400);
        }

        if ($endYear - $startYear > 10) {
            return response()->json([
                'error' => 'Range cannot exceed 10 years'
            ], 400);
        }

        try {
            $allHolidays = [];

            for ($year = $startYear; $year <= $endYear; $year++) {
                $cacheKey = "ph_holidays_{$year}";
                $allHolidays[$year] = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($year) {
                    return $this->fetchHolidaysFromApi($year);
                });
            }

            return response()->json([
                'success' => true,
                'start_year' => $startYear,
                'end_year' => $endYear,
                'holidays' => $allHolidays
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch holidays',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
// ─── CheckData ─────────────────────────────────────────────────────
    public function checkDate(Request $request): JsonResponse
    {
        $date = $request->query('date');

        if (!$date) {
            return response()->json([
                'error' => 'Date parameter is required (format: YYYY-MM-DD)'
            ], 400);
        }

        try {
            $dateObj = Carbon::parse($date);
            $year = $dateObj->year;
            $formattedDate = $dateObj->toDateString();

            $cacheKey = "ph_holidays_{$year}";
            $holidays = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($year) {
                return $this->fetchHolidaysFromApi($year);
            });

            $holiday = collect($holidays)->firstWhere('date', $formattedDate);

            return response()->json([
                'success' => true,
                'date' => $formattedDate,
                'is_holiday' => $holiday !== null,
                'holiday' => $holiday
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invalid date format. Use YYYY-MM-DD'
            ], 400);
        }
    }
// ─── Next ─────────────────────────────────────────────────────
    public function next(): JsonResponse
    {
        try {
            $today = now()->toDateString();
            $year = now()->year;

            $cacheKey = "ph_holidays_{$year}";
            $holidays = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($year) {
                return $this->fetchHolidaysFromApi($year);
            });

            $nextHoliday = collect($holidays)
                ->filter(fn($holiday) => $holiday['date'] >= $today)
                ->first();

            if (!$nextHoliday && $year < 2100) {
                $nextCacheKey = "ph_holidays_" . ($year + 1);
                $nextYearHolidays = Cache::remember($nextCacheKey, self::CACHE_DURATION, function () use ($year) {
                    return $this->fetchHolidaysFromApi($year + 1);
                });
                $nextHoliday = collect($nextYearHolidays)->first();
            }

            return response()->json([
                'success' => true,
                'next_holiday' => $nextHoliday ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch next holiday',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
// ─── Search ─────────────────────────────────────────────────────
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $year = $request->query('year', now()->year);

        if (!$query) {
            return response()->json([
                'error' => 'Search query (q) parameter is required'
            ], 400);
        }

        try {
            $cacheKey = "ph_holidays_{$year}";
            $holidays = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($year) {
                return $this->fetchHolidaysFromApi($year);
            });

            $results = collect($holidays)
                ->filter(fn($holiday) => 
                    str_contains(strtolower($holiday['englishName']), strtolower($query)) ||
                    str_contains(strtolower($holiday['localName']), strtolower($query))
                )
                ->values();

            return response()->json([
                'success' => true,
                'query' => $query,
                'year' => $year,
                'count' => $results->count(),
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
// ─── ClearCache ─────────────────────────────────────────────────────
    public function clearCache(?int $year = null): JsonResponse
    {
        if ($year) {
            Cache::forget("ph_holidays_{$year}");
            return response()->json([
                'success' => true,
                'message' => "Cache cleared for year {$year}"
            ]);
        }

        for ($y = 1900; $y <= 2100; $y++) {
            Cache::forget("ph_holidays_{$y}");
        }

        return response()->json([
            'success' => true,
            'message' => 'All holiday caches cleared'
        ]);
    }
// ─── FetchHolidaysFromApi ─────────────────────────────────────────────────────
    private function fetchHolidaysFromApi(int $year): array
    {
        $response = Http::timeout(10)
            ->retry(2, 100)
            ->get(self::API_URL . "/{$year}/" . self::COUNTRY_CODE);

        if (!$response->successful()) {
            throw new \Exception("API returned status: {$response->status()}");
        }

        return $response->json();
    }
}