<?php

namespace App\Services\Positions;

use App\Models\PlantillaPosition;
use App\Repositories\Positions\PlantillaPositionRepository;
use App\Repositories\Recruitment\PlantillaPosting\PlantillaPostingRepository;
use Illuminate\Validation\ValidationException;

class PlantillaItemNumberService
{
    private const SHIFTABLE_PATTERN = '/^[1-9][0-9]{0,8}$/';

    public function __construct(
        private readonly PlantillaPositionRepository $positions,
        private readonly PlantillaPostingRepository $postings,
        private readonly PlantillaPositionFormatterService $formatter,
    ) {
    }

    public function makeRoomFor(string $baseItemNumber): array
    {
        $existing = array_map('strtoupper', $this->positions->lockedBaseItemNumbers());

        if (! in_array(strtoupper($baseItemNumber), $existing, true)) {
            return [];
        }

        if (! $this->isShiftable($baseItemNumber)) {
            throw ValidationException::withMessages([
                'base_item_number' => "Item number {$baseItemNumber} already exists.",
            ]);
        }

        $taken = array_flip(array_filter($existing, fn ($number) => $this->isShiftable((string) $number)));

        $first = (int) $baseItemNumber;
        $free  = $first;
        while (isset($taken[(string) $free])) {
            $free++;
        }

        $this->positions->purgeTrashedForBaseItem((string) $free);

        $renumbered = [];
        for ($current = $free - 1; $current >= $first; $current--) {
            $from = (string) $current;
            $to   = (string) ($current + 1);

            $this->renumber($from, $to);
            $renumbered[$from] = $to;
        }

        foreach ($renumbered as $to) {
            $this->syncSlotNames($to);
        }

        return $renumbered;
    }

    public function syncSlotNames(string $baseItemNumber): void
    {
        $slots = $this->positions->activeForBaseItemNumber($baseItemNumber);

        foreach ($slots as $slot) {
            $name = $this->formatter->buildSlotName($slot->base_item_number, (int) $slot->slot_number, $slots->count());

            if ($slot->position_slot_name !== $name) {
                $slot->update(['position_slot_name' => $name]);
            }
        }

        $this->postings->refreshSlotNames($baseItemNumber);
    }

    private function renumber(string $from, string $to): void
    {
        // Soft-deleted slots move with their item so a restore can't land on another item's number.
        foreach ($this->positions->withTrashedForBaseItemNumber($from) as $slot) {
            $slot->update([
                'base_item_number'   => $to,
                'position_slot_name' => $this->renamedSlot($slot, $from, $to),
            ]);
        }

        $this->postings->renumberBaseItem($from, $to);
    }

    private function renamedSlot(PlantillaPosition $slot, string $from, string $to): string
    {
        if ($slot->position_slot_name === $from) {
            return $to;
        }

        if (str_starts_with($slot->position_slot_name, "{$from}-")) {
            return $to . substr($slot->position_slot_name, strlen($from));
        }

        return "{$to}-{$slot->slot_number}";
    }

    private function isShiftable(string $baseItemNumber): bool
    {
        return preg_match(self::SHIFTABLE_PATTERN, $baseItemNumber) === 1;
    }
}