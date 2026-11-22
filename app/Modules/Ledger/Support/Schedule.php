<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Support;

use App\Modules\Ledger\Models\Frequency;

final readonly class Schedule
{
    private const DAYS_PER_WEEK = 7;

    private const MONTHS_PER_YEAR = 12;

    public function __construct(
        public Frequency $frequency,
        public int $interval,
        public string $startsOn,
        public ?string $endsOn,
    ) {}

    public function dueDate(int $index): ?string
    {
        $start = BookDay::parse($this->startsOn);
        $steps = $index * $this->interval;

        $due = match ($this->frequency) {
            Frequency::Weekly => $start->addWeeks($steps),
            Frequency::Monthly => $start->addMonthsNoOverflow($steps),
            Frequency::Yearly => $start->addYearsNoOverflow($steps),
        };
        $date = $due->toDateString();

        return $this->endsOn !== null && $date > $this->endsOn ? null : $date;
    }

    public function firstOnOrAfter(string $date): ?string
    {
        for ($index = $this->indexBefore($date); ; $index++) {
            $due = $this->dueDate($index);

            if ($due === null || $due >= $date) {
                return $due;
            }
        }
    }

    public function firstAfter(string $date): ?string
    {
        return $this->firstOnOrAfter(BookDay::addDays($date, 1));
    }

    private function indexBefore(string $date): int
    {
        if ($date <= $this->startsOn) {
            return 0;
        }

        $start = BookDay::parse($this->startsOn);
        $target = BookDay::parse($date);

        $units = match ($this->frequency) {
            Frequency::Weekly => intdiv((int) $start->diffInDays($target), self::DAYS_PER_WEEK),
            Frequency::Monthly => ($target->year - $start->year) * self::MONTHS_PER_YEAR + $target->month - $start->month,
            Frequency::Yearly => $target->year - $start->year,
        };

        return max(0, intdiv($units, $this->interval) - 1);
    }
}
