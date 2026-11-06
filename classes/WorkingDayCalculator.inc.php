<?php
/**
 * @file plugins/generic/editorialReminderDashboard/classes/WorkingDayCalculator.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkingDayCalculator
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Calculates working days (Monday-Friday excluding configured holidays),
 * due dates, review status, and copyediting target issue status.
 */

class WorkingDayCalculator
{
	/** @var array<string, bool> Map of 'Y-m-d' => true for excluded holidays */
	protected $_holidayMap = [];

	/** @var DateTimeZone Active timezone for calculations */
	protected $_timeZone;

    /**
     * Constructor.
     *
     * @param array $holidayDates List of holiday date strings ('Y-m-d')
     * @param string|null $timezoneName Timezone identifier (default 'Asia/Jakarta')
     */
    public function __construct($holidayDates = [], $timezoneName = 'Asia/Jakarta')
    {
        foreach ((array) $holidayDates as $dateStr) {
            if (!empty($dateStr)) {
                $normalized = substr(trim((string) $dateStr), 0, 10);
                $this->_holidayMap[$normalized] = true;
            }
        }

        try {
            $this->_timeZone = new DateTimeZone($timezoneName ?: 'Asia/Jakarta');
        } catch (Exception $e) {
            $this->_timeZone = new DateTimeZone('Asia/Jakarta');
        }
    }

    /**
     * Get the active DateTimeZone.
     *
     * @return DateTimeZone
     */
    public function getTimeZone()
    {
        return $this->_timeZone;
    }

    /**
     * Return today's date string ('Y-m-d') in the configured timezone.
     *
     * @param string|null $referenceDate Optional override date ('Y-m-d')
     * @return string
     */
    public function getTodayDateString($referenceDate = null)
    {
        if (!empty($referenceDate)) {
            return substr(trim($referenceDate), 0, 10);
        }
        $now = new DateTime('now', $this->_timeZone);
        return $now->format('Y-m-d');
    }

	/**
	 * Determine whether a given date ('Y-m-d' or DateTimeInterface) is a working day.
	 * Working days are Monday (1) through Friday (5) and not present in the holiday table.
	 *
	 * @param DateTimeInterface|string $date
	 * @return bool
	 */
	public function isWorkingDay($date)
	{
		$dt = $date instanceof DateTimeInterface ? clone $date : new DateTime(substr((string) $date, 0, 10), $this->_timeZone);

		$dayOfWeek = (int) $dt->format('N'); // 1 (Mon) .. 7 (Sun)
		if ($dayOfWeek >= 6) {
			return false;
		}

		$ymd = $dt->format('Y-m-d');
		if (isset($this->_holidayMap[$ymd])) {
			return false;
		}

		return true;
	}

	/**
	 * Add $workingDays working days to $startDate.
	 * Day 1 of the count is the first working day strictly after $startDate.
	 * Example: Start Thu 2026-10-08 + 21 working days = Fri 2026-11-06.
	 *
	 * @param string $startDate Date string ('Y-m-d' or 'Y-m-d H:i:s')
	 * @param int $workingDays Number of working days to add
	 * @return string Due date in 'Y-m-d' format
	 */
	public function addWorkingDays($startDate, $workingDays)
	{
		$dt = new DateTime(substr((string) $startDate, 0, 10), $this->_timeZone);
		$remaining = max(0, (int) $workingDays);

		while ($remaining > 0) {
			$dt->modify('+1 day');
			if ($this->isWorkingDay($dt)) {
				$remaining--;
			}
		}

		return $dt->format('Y-m-d');
	}

	/**
	 * Count working days strictly after $startDate up to and including $endDate.
	 * If $endDate <= $startDate, returns 0.
	 *
	 * @param string $startDate Start date ('Y-m-d')
	 * @param string $endDate End date ('Y-m-d')
	 * @return int Number of working days elapsed
	 */
	public function countWorkingDaysBetween($startDate, $endDate)
	{
		$startYmd = substr((string) $startDate, 0, 10);
		$endYmd = substr((string) $endDate, 0, 10);

		if ($endYmd <= $startYmd) {
			return 0;
		}

		$cursor = new DateTime($startYmd, $this->_timeZone);
		$target = new DateTime($endYmd, $this->_timeZone);
		$count = 0;

		while ($cursor < $target) {
			$cursor->modify('+1 day');
			if ($this->isWorkingDay($cursor)) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Evaluate review round status, due date, and remaining/overdue working days.
	 *
	 * @param string $startDate Round start date ('Y-m-d' or datetime)
	 * @param int $limitWorkingDays Maximum working days for the round (e.g. 21 for Round 1, 7 for Round 2+)
	 * @param int $warningWorkingDays Almost-due threshold in working days (e.g. 3 for Round 1, 2 for Round 2+)
	 * @param string|null $referenceDate Optional reference date ('Y-m-d') instead of today
	 * @return array {
	 *   startDate: string,
	 *   dueDate: string,
	 *   elapsedWorkingDays: int,
	 *   remainingWorkingDays: int,
	 *   overdueWorkingDays: int,
	 *   status: string ('normal'|'almost_due'|'overdue'),
	 *   urgencyScore: int
	 * }
	 */
	public function evaluateReviewRound($startDate, $limitWorkingDays, $warningWorkingDays, $referenceDate = null)
	{
		$startYmd = substr((string) $startDate, 0, 10);
		$todayYmd = $this->getTodayDateString($referenceDate);
		$dueYmd = $this->addWorkingDays($startYmd, $limitWorkingDays);
		$elapsed = $this->countWorkingDaysBetween($startYmd, $todayYmd);

		if ($todayYmd <= $dueYmd) {
			$remaining = $this->countWorkingDaysBetween($todayYmd, $dueYmd);
			$overdue = 0;
			$status = $remaining <= (int) $warningWorkingDays ? 'almost_due' : 'normal';
			// Higher urgencyScore = more urgent
			$urgencyScore = $status === 'almost_due' ? 5000 - $remaining : 1000 - $remaining;
		} else {
			$remaining = 0;
			$overdue = $this->countWorkingDaysBetween($dueYmd, $todayYmd);
			if ($overdue >= 1) {
				$status = 'overdue';
				$urgencyScore = 10000 + $overdue;
			} else {
				// Due date was on Friday and today is weekend/holiday before next working day
				$status = 'almost_due';
				$urgencyScore = 5000;
			}
		}

		return [
			'startDate' => $startYmd,
			'dueDate' => $dueYmd,
			'elapsedWorkingDays' => $elapsed,
			'remainingWorkingDays' => $remaining,
			'overdueWorkingDays' => $overdue,
			'status' => $status,
			'urgencyScore' => $urgencyScore,
		];
	}

	/**
	 * Evaluate Copyediting stage status based on target publication date H in calendar days.
	 *
	 * @param string|null $targetDate Target issue publication date H ('Y-m-d') or null if not assigned
	 * @param int $reminderCalendarDays Reminder threshold in calendar days before H (default 10 for H-10)
	 * @param string|null $referenceDate Optional reference date ('Y-m-d') instead of today
	 * @return array {
	 *   targetDate: string|null,
	 *   diffCalendarDays: int|null,
	 *   remainingCalendarDays: int|null,
	 *   overdueCalendarDays: int|null,
	 *   isDueToday: bool,
	 *   status: string ('normal'|'almost_due'|'overdue'|'no_target'),
	 *   urgencyScore: int
	 * }
	 */
	public function evaluateCopyediting($targetDate, $reminderCalendarDays = 10, $referenceDate = null)
	{
		if (empty($targetDate)) {
			return [
				'targetDate' => null,
				'diffCalendarDays' => null,
				'remainingCalendarDays' => null,
				'overdueCalendarDays' => null,
				'isDueToday' => false,
				'status' => 'no_target',
				'urgencyScore' => 2500, // Highlighted above normal articles so it is not missed
			];
		}

        $targetYmd = substr((string) $targetDate, 0, 10);
        $todayYmd = $this->getTodayDateString($referenceDate);

        $todayDt = new DateTime($todayYmd, $this->_timeZone);
        $targetDt = new DateTime($targetYmd, $this->_timeZone);
        $interval = $todayDt->diff($targetDt);
        $diffDays = (int) $interval->format('%r%a'); // Positive if target is in future, negative if past

        if ($diffDays > (int) $reminderCalendarDays) {
            return [
                'targetDate' => $targetYmd,
                'diffCalendarDays' => $diffDays,
                'remainingCalendarDays' => $diffDays,
                'overdueCalendarDays' => 0,
                'isDueToday' => false,
                'status' => 'normal',
                'urgencyScore' => max(1, 1000 - $diffDays),
            ];
        } elseif ($diffDays >= 0) {
            // H-10 to H-1 (almost due) and H+0 (due today warning)
            return [
                'targetDate' => $targetYmd,
                'diffCalendarDays' => $diffDays,
                'remainingCalendarDays' => $diffDays,
                'overdueCalendarDays' => 0,
                'isDueToday' => $diffDays === 0,
                'status' => 'almost_due',
                'urgencyScore' => 5000 - $diffDays,
            ];
        } else {
            $overdueDays = abs($diffDays);
            return [
                'targetDate' => $targetYmd,
                'diffCalendarDays' => $diffDays,
                'remainingCalendarDays' => 0,
                'overdueCalendarDays' => $overdueDays,
                'isDueToday' => false,
                'status' => 'overdue',
                'urgencyScore' => 10000 + $overdueDays,
            ];
        }
    }
}
