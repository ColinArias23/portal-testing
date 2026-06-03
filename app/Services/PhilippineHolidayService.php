<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PhilippineHolidayService
{
    private const API_URL = 'https://date.nager.at/api/v3/publicholidays';
    private const COUNTRY_CODE = 'PH';
    private const CACHE_DURATION = 24 * 60; // 24 hours

    /**
     * Get all holidays for a specific year
     */
    public function getHolidaysForYear(int $year): array
    {
        return Cache::remember("ph_holidays_{$year}", self::CACHE_DURATION, fn() => 
            $this->fetchFromApi($year)
        );
    }

    /**
     * Check if a date is a holiday
     */
    public function isHoliday($date): bool
    {
        $dateObj = $date instanceof Carbon ? $date : Carbon::parse($date);
        $year = $dateObj->year;
        $dateString = $dateObj->toDateString();

        $holidays = $this->getHolidaysForYear($year);

        return collect($holidays)
            ->pluck('date')
            ->contains($dateString);
    }

    /**
     * Get holiday details if date is a holiday
     */
    public function getHolidayDetails($date): ?array
    {
        $dateObj = $date instanceof Carbon ? $date : Carbon::parse($date);
        $year = $dateObj->year;
        $dateString = $dateObj->toDateString();

        $holidays = $this->getHolidaysForYear($year);

        return collect($holidays)
            ->firstWhere('date', $dateString);
    }

    /**
     * Get all holidays in a specific month
     */
    public function getHolidaysInMonth(int $month, int $year): array
    {
        $holidays = $this->getHolidaysForYear($year);

        return collect($holidays)
            ->filter(fn($holiday) => Carbon::parse($holiday['date'])->month === $month)
            ->sortBy('date')
            ->values()
            ->toArray();
    }

    /**
     * Get next upcoming holiday from today
     */
    public function getNextHoliday(): ?array
    {
        $today = now()->toDateString();
        $year = now()->year;

        $holidays = $this->getHolidaysForYear($year);

        $nextHoliday = collect($holidays)
            ->filter(fn($holiday) => $holiday['date'] >= $today)
            ->first();

        // Check next year if none found
        if (!$nextHoliday && $year < 2100) {
            $nextYearHolidays = $this->getHolidaysForYear($year + 1);
            $nextHoliday = collect($nextYearHolidays)->first();
        }

        return $nextHoliday;
    }

    /**
     * Get holidays within a date range
     */
    public function getHolidaysBetween($startDate, $endDate): array
    {
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $end = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        $startYear = $start->year;
        $endYear = $end->year;

        $holidays = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearHolidays = $this->getHolidaysForYear($year);
            $holidays = array_merge($holidays, $yearHolidays);
        }

        return collect($holidays)
            ->filter(function ($holiday) use ($start, $end) {
                $holidayDate = Carbon::parse($holiday['date']);
                return $holidayDate->isBetween($start, $end);
            })
            ->sortBy('date')
            ->values()
            ->toArray();
    }

    /**
     * Count holidays in a year
     */
    public function countHolidaysInYear(int $year): int
    {
        $holidays = $this->getHolidaysForYear($year);
        return count($holidays);
    }

    /**
     * Get working days between two dates (excludes weekends and holidays)
     */
    public function getWorkingDaysBetween($startDate, $endDate): int
    {
        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $end = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        $holidays = $this->getHolidaysBetween($start, $end);
        $holidayDates = collect($holidays)->pluck('date')->toArray();

        $workingDays = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            // Check if it's a weekday and not a holiday
            if ($current->isWeekday() && !in_array($current->toDateString(), $holidayDates)) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Search holidays by name
     */
    public function searchHolidays(string $query, int $year): array
    {
        $holidays = $this->getHolidaysForYear($year);
        $queryLower = strtolower($query);

        return collect($holidays)
            ->filter(fn($holiday) => 
                str_contains(strtolower($holiday['englishName']), $queryLower) ||
                str_contains(strtolower($holiday['localName']), $queryLower)
            )
            ->values()
            ->toArray();
    }

    /**
     * Get holiday statistics for a year
     */
    public function getYearStatistics(int $year): array
    {
        $holidays = $this->getHolidaysForYear($year);

        return [
            'year' => $year,
            'total_holidays' => count($holidays),
            'national_holidays' => collect($holidays)->where('global', true)->count(),
            'special_holidays' => collect($holidays)->where('global', false)->count(),
            'by_month' => $this->groupHolidaysByMonth($holidays),
            'working_days' => $this->getWorkingDaysInYear($year, $holidays),
        ];
    }

    /**
     * Check if there's a holiday coming up in the next N days
     */
    public function hasHolidayUpcoming(int $days = 7): bool
    {
        $today = now()->toDateString();
        $futureDate = now()->addDays($days)->toDateString();

        $holidays = $this->getHolidaysBetween($today, $futureDate);

        return count($holidays) > 0;
    }

    /**
     * Get all holidays coming up in the next N days
     */
    public function getUpcomingHolidays(int $days = 30): array
    {
        $today = now();
        $futureDate = now()->addDays($days);

        return $this->getHolidaysBetween($today, $futureDate);
    }

    /**
     * Clear cached holidays
     */
    public function clearCache(?int $year = null): void
    {
        if ($year) {
            Cache::forget("ph_holidays_{$year}");
        } else {
            for ($y = 1900; $y <= 2100; $y++) {
                Cache::forget("ph_holidays_{$y}");
            }
        }
    }

    /**
     * Fetch holidays from API
     */
    private function fetchFromApi(int $year): array
    {
        $response = Http::timeout(10)
            ->retry(2, 100)
            ->get(self::API_URL . "/{$year}/" . self::COUNTRY_CODE);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch holidays: API returned {$response->status()}");
        }

        return $response->json();
    }

    /**
     * Group holidays by month
     */
    private function groupHolidaysByMonth(array $holidays): array
    {
        return collect($holidays)
            ->groupBy(fn($holiday) => Carbon::parse($holiday['date'])->format('m'))
            ->map(function ($group, $month) {
                $monthName = Carbon::parse("2024-{$month}-01")->format('F');
                return [
                    'month' => (int) $month,
                    'month_name' => $monthName,
                    'count' => $group->count(),
                    'holidays' => $group->values()->toArray()
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get working days in a year
     */
    private function getWorkingDaysInYear(int $year, array $holidays): int
    {
        $holidayDates = collect($holidays)->pluck('date')->toArray();
        
        $startDate = Carbon::createFromDate($year, 1, 1);
        $endDate = Carbon::createFromDate($year, 12, 31);

        return $this->countWorkingDays($startDate, $endDate, $holidayDates);
    }

    /**
     * Count working days
     */
    private function countWorkingDays(Carbon $start, Carbon $end, array $holidayDates): int
    {
        $workingDays = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday() && !in_array($current->toDateString(), $holidayDates)) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }
}