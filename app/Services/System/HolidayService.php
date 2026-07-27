<?php

namespace App\Services\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HolidayService
{
    private const API_URL = 'https://date.nager.at/api/v3/publicholidays';
    private const COUNTRY_CODE = 'PH';
    private const CACHE_DURATION = 24 * 60;

    public function forYear(int $year): array
    {
        return Cache::remember("ph_holidays_{$year}", self::CACHE_DURATION, fn () => $this->fetchFromApi($year));
    }

    public function forRange(int $startYear, int $endYear): array
    {
        $allHolidays = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $allHolidays[$year] = $this->forYear($year);
        }

        return $allHolidays;
    }

    public function findOnDate(string $date): ?array
    {
        $dateObj = Carbon::parse($date);
        $formattedDate = $dateObj->toDateString();

        $holidays = $this->forYear($dateObj->year);

        return collect($holidays)->firstWhere('date', $formattedDate);
    }

    public function next(): ?array
    {
        $today = now()->toDateString();
        $year  = now()->year;

        $holidays = $this->forYear($year);

        $nextHoliday = collect($holidays)
            ->filter(fn ($holiday) => $holiday['date'] >= $today)
            ->first();

        if (! $nextHoliday && $year < 2100) {
            $nextYearHolidays = $this->forYear($year + 1);
            $nextHoliday = collect($nextYearHolidays)->first();
        }

        return $nextHoliday;
    }

    public function search(string $query, int $year): array
    {
        $holidays = $this->forYear($year);
        $needle   = strtolower($query);

        return collect($holidays)
            ->filter(fn ($holiday) => str_contains(strtolower($holiday['englishName']), $needle)
                || str_contains(strtolower($holiday['localName']), $needle))
            ->values()
            ->all();
    }

    public function clearCacheForYear(int $year): void
    {
        Cache::forget("ph_holidays_{$year}");
    }

    public function clearAllCache(): void
    {
        for ($y = 1900; $y <= 2100; $y++) {
            Cache::forget("ph_holidays_{$y}");
        }
    }

    private function fetchFromApi(int $year): array
    {
        $response = Http::timeout(10)
            ->retry(2, 100)
            ->get(self::API_URL . "/{$year}/" . self::COUNTRY_CODE);

        if (! $response->successful()) {
            throw new \Exception("API returned status: {$response->status()}");
        }

        return $response->json();
    }
}
