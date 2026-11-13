<?php
/**
 * @file plugins/generic/editorialReminderDashboard/classes/EditorialReminderService.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderService
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Core domain service for calculations, evaluations, aggregations, and dashboard items
 * for the Editorial Reminder Dashboard plugin.
 */

require_once __DIR__ . '/WorkingDayCalculator.inc.php';
require_once __DIR__ . '/EditorialReminderDAO.inc.php';

class EditorialReminderService
{
    /** @var EditorialReminderDashboardPlugin */
    protected $_plugin;

    /** @var EditorialReminderDAO */
    protected $_dao;

    /**
     * Constructor.
     *
     * @param EditorialReminderDashboardPlugin $plugin
     * @param EditorialReminderDAO|null $dao
     */
    public function __construct($plugin, $dao = null)
    {
        $this->_plugin = $plugin;
        $this->_dao = $dao ?: new EditorialReminderDAO();
    }

    /**
     * Get the DAO instance.
     *
     * @return EditorialReminderDAO
     */
    public function getDAO()
    {
        return $this->_dao;
    }

    /**
     * Normalize a raw working days value (array or comma-separated string of ISO-8601 day numbers 1..7).
     * Defaults to Monday through Friday ([1, 2, 3, 4, 5]) if empty or invalid.
     *
     * @param array|string|null $raw
     * @return array<int> Sorted unique array of ISO-8601 day integers (1=Mon .. 7=Sun)
     */
    public function normalizeWorkingDays($raw)
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        }

        $days = [];
        if (is_array($raw)) {
            foreach ($raw as $val) {
                $d = (int) $val;
                if ($d >= 1 && $d <= 7) {
                    $days[$d] = $d;
                }
            }
        }

        if (empty($days)) {
            return [1, 2, 3, 4, 5];
        }

        ksort($days);
        return array_values($days);
    }

    /**
     * Get effective settings for a journal (or global defaults when $contextId = 0).
     *
     * @param int $contextId Journal ID or 0 for site-level
     * @return array
     */
    public function getContextSettings($contextId = 0)
    {
        $ctxId = (int) $contextId;
        $defaultTz = Config::getVar('general', 'time_zone') ?: 'Asia/Jakarta';
        if ($defaultTz === 'UTC' || empty($defaultTz)) {
            $defaultTz = 'Asia/Jakarta';
        }

        // Site-level defaults
        $globalRound1Limit = (int) $this->_plugin->getSetting(0, 'round1Limit') ?: 21;
        $globalRound2Limit = (int) $this->_plugin->getSetting(0, 'round2Limit') ?: 7;
        $globalRound1Warning = (int) $this->_plugin->getSetting(0, 'round1Warning') ?: 3;
        $globalRound2Warning = (int) $this->_plugin->getSetting(0, 'round2Warning') ?: 2;
        $globalCopyeditingWarning = (int) $this->_plugin->getSetting(0, 'copyeditingWarning') ?: 10;
        $globalTimezone = (string) $this->_plugin->getSetting(0, 'timezone') ?: $defaultTz;
        $globalWorkingDaysRaw = $this->_plugin->getSetting(0, 'workingDays');
        $globalWorkingDays = $this->normalizeWorkingDays($globalWorkingDaysRaw);
        $directorUsernames = (string) $this->_plugin->getSetting(0, 'directorUsernames');
        $enableDailyDigest = (bool) $this->_plugin->getSetting(0, 'enableDailyDigest');
        $enableWeeklyDigest = (bool) $this->_plugin->getSetting(0, 'enableWeeklyDigest');

        if ($ctxId === 0) {
            return [
                'round1Limit' => max(14, min(21, $globalRound1Limit)),
                'round2Limit' => max(1, $globalRound2Limit),
                'round1Warning' => max(1, $globalRound1Warning),
                'round2Warning' => max(1, $globalRound2Warning),
                'copyeditingWarning' => max(1, $globalCopyeditingWarning),
                'timezone' => $globalTimezone,
                'workingDays' => $globalWorkingDays,
                'workingDaysStr' => implode(',', $globalWorkingDays),
                'workingDaysMap' => array_fill_keys($globalWorkingDays, true),
                'directorUsernames' => $directorUsernames,
                'enableDailyDigest' => $enableDailyDigest,
                'enableWeeklyDigest' => $enableWeeklyDigest,
            ];
        }

        $r1Limit = $this->_plugin->getSetting($ctxId, 'round1Limit');
        $r2Limit = $this->_plugin->getSetting($ctxId, 'round2Limit');
        $r1Warn = $this->_plugin->getSetting($ctxId, 'round1Warning');
        $r2Warn = $this->_plugin->getSetting($ctxId, 'round2Warning');
        $ceWarn = $this->_plugin->getSetting($ctxId, 'copyeditingWarning');
        $tz = $this->_plugin->getSetting($ctxId, 'timezone');
        $jWorkingDaysRaw = $this->_plugin->getSetting($ctxId, 'workingDays');
        $jDaily = $this->_plugin->getSetting($ctxId, 'enableDailyDigest');
        $jWeekly = $this->_plugin->getSetting($ctxId, 'enableWeeklyDigest');

        $round1Limit = $r1Limit !== null && $r1Limit !== '' ? (int) $r1Limit : $globalRound1Limit;
        $round1Limit = max(14, min(21, $round1Limit)); // Allowed range: 14-21 working days

        $workingDays = $jWorkingDaysRaw !== null && $jWorkingDaysRaw !== '' ? $this->normalizeWorkingDays($jWorkingDaysRaw) : $globalWorkingDays;

        $workingDays = $jWorkingDaysRaw !== null && $jWorkingDaysRaw !== '' ? $this->normalizeWorkingDays($jWorkingDaysRaw) : $globalWorkingDays;

        return [
            'round1Limit' => $round1Limit,
            'round2Limit' => $r2Limit !== null && $r2Limit !== '' ? max(1, (int) $r2Limit) : $globalRound2Limit,
            'round1Warning' => $r1Warn !== null && $r1Warn !== '' ? max(1, (int) $r1Warn) : $globalRound1Warning,
            'round2Warning' => $r2Warn !== null && $r2Warn !== '' ? max(1, (int) $r2Warn) : $globalRound2Warning,
            'copyeditingWarning' => $ceWarn !== null && $ceWarn !== '' ? max(1, (int) $ceWarn) : $globalCopyeditingWarning,
            'timezone' => !empty($tz) ? (string) $tz : $globalTimezone,
            'workingDays' => $workingDays,
            'workingDaysStr' => implode(',', $workingDays),
            'workingDaysMap' => array_fill_keys($workingDays, true),
            'directorUsernames' => $directorUsernames,
            'enableDailyDigest' => $jDaily !== null && $jDaily !== '' ? (bool) $jDaily : $enableDailyDigest,
            'enableWeeklyDigest' => $jWeekly !== null && $jWeekly !== '' ? (bool) $jWeekly : $enableWeeklyDigest,
        ];
    }

    /**
     * Determine whether a user has Director / Site-level Executive access.
     *
     * @param User|null $user
     * @return bool
     */
    public function isUserDirectorOrSiteAdmin($user)
    {
        if (!$user) {
            return false;
        }
        if (Validation::isSiteAdmin()) {
            return true;
        }

        $directorListRaw = (string) $this->_plugin->getSetting(0, 'directorUsernames');
        if (trim($directorListRaw) === '') {
            return false;
        }

        $tokens = preg_split('/[\s,;]+/', strtolower(trim($directorListRaw)), -1, PREG_SPLIT_NO_EMPTY);
        $username = strtolower((string) $user->getUsername());
        $userIdStr = (string) $user->getId();

        return in_array($username, $tokens, true) || in_array($userIdStr, $tokens, true);
    }

    /**
     * Build complete dashboard data for a single journal.
     *
     * @param int $contextId Journal ID
     * @param array $filters Active filter parameters (stage, status, editorId, issueId, search, sort)
     * @param int|null $restrictToEditorId User ID if user is a Section Editor
     * @param bool $canViewReviewerIdentity Whether current user can see reviewer names (Privacy)
     * @param string $locale Current locale
     * @return array
     */
    public function getJournalDashboardData($contextId, $filters = [], $restrictToEditorId = null, $canViewReviewerIdentity = true, $locale = 'en_US')
    {
        $ctxId = (int) $contextId;
        $settings = $this->getContextSettings($ctxId);
        $holidays = $this->_dao->getHolidays($ctxId);
        $holidayDates = array_column($holidays, 'date');
        $calculator = new WorkingDayCalculator($holidayDates, $settings['timezone'], $settings['workingDays']);

        $issuesMap = $this->_dao->getIssuesWithTargetDates($ctxId, $locale);
        $futureIssues = [];
        $backIssues = [];
        foreach ($issuesMap as $issId => $iss) {
            if ((int) $iss['published'] === 0) {
                $futureIssues[$issId] = $iss;
            } else {
                $backIssues[$issId] = $iss;
            }
        }

        $batch = $this->_dao->getMonitoredSubmissionsBatch($ctxId, $restrictToEditorId, $locale);
        $allItems = $this->_processSubmissionsBatch($batch, [$ctxId => $settings], [$ctxId => $calculator], $issuesMap, $canViewReviewerIdentity, $locale);

        // Compute overall summary cards before filter
        $summary = [
            'total' => count($allItems),
            'reviewCount' => 0,
            'copyeditingCount' => 0,
            'normal' => 0,
            'almostDue' => 0,
            'overdue' => 0,
            'noTarget' => 0,
        ];

        $editorsFilterMap = [];
        foreach ($allItems as $item) {
            if ($item['stageKey'] === 'review') {
                $summary['reviewCount']++;
            } else {
                $summary['copyeditingCount']++;
            }

            if ($item['status'] === 'normal') {
                $summary['normal']++;
            } elseif ($item['status'] === 'almost_due') {
                $summary['almostDue']++;
            } elseif ($item['status'] === 'overdue') {
                $summary['overdue']++;
            } elseif ($item['status'] === 'no_target') {
                $summary['noTarget']++;
            }

            foreach ($item['editors'] as $ed) {
                $editorsFilterMap[$ed['userId']] = $ed['fullName'];
            }
        }
        asort($editorsFilterMap);

        // Apply active filters
        $filteredItems = $this->filterAndSortItems($allItems, $filters);

        return [
            'contextId' => $ctxId,
            'settings' => $settings,
            'holidays' => $holidays,
            'issues' => $issuesMap,
            'allIssues' => $issuesMap,
            'futureIssues' => $futureIssues,
            'backIssues' => $backIssues,
            'unpublishedIssues' => $futureIssues,
            'editorsFilterMap' => $editorsFilterMap,
            'summary' => $summary,
            'items' => $filteredItems,
            'allItemsCount' => count($allItems),
        ];
    }

    /**
     * Build complete aggregated data for the Site-level Publishing House Dashboard.
     *
     * @param array $filters Active site filters (stage, sort)
     * @param string $locale Current locale
     * @return array
     */
    public function getSiteDashboardData($filters = [], $locale = 'en_US')
    {
        $journals = $this->_dao->getEnabledJournals($locale);
        if (empty($journals)) {
            return [
                'summary' => [
                    'journalCount' => 0,
                    'total' => 0,
                    'normal' => 0,
                    'almostDue' => 0,
                    'overdue' => 0,
                    'noTarget' => 0,
                ],
                'journalRows' => [],
                'urgentItems' => [],
                'allItems' => [],
            ];
        }

        $journalIds = array_keys($journals);
        $settingsByJournal = [];
        $calculatorsByJournal = [];

        $globalHolidays = $this->_dao->getHolidays(0);
        $globalHolidayDates = array_column($globalHolidays, 'date');

        foreach ($journalIds as $jid) {
            $jSettings = $this->getContextSettings($jid);
            $settingsByJournal[$jid] = $jSettings;
            $jHolidays = $this->_dao->getHolidays($jid);
            $jHolidayDates = array_unique(array_merge($globalHolidayDates, array_column($jHolidays, 'date')));
            $calculatorsByJournal[$jid] = new WorkingDayCalculator($jHolidayDates, $jSettings['timezone'], $jSettings['workingDays']);
        }

        $issuesMap = $this->_dao->getIssuesWithTargetDates($journalIds, $locale);
        $batch = $this->_dao->getMonitoredSubmissionsBatch($journalIds, null, $locale);
        $allItems = $this->_processSubmissionsBatch($batch, $settingsByJournal, $calculatorsByJournal, $issuesMap, true, $locale, $journals);

        $stageFilter = isset($filters['stage']) ? trim((string) $filters['stage']) : 'all';
        if ($stageFilter === 'review' || $stageFilter === 'copyediting') {
            $allItems = array_values(
                array_filter($allItems, function ($item) use ($stageFilter) {
                    return $item['stageKey'] === $stageFilter;
                }),
            );
        }

        $siteSummary = [
            'journalCount' => count($journals),
            'total' => 0,
            'normal' => 0,
            'almostDue' => 0,
            'overdue' => 0,
            'noTarget' => 0,
        ];

        $journalStats = [];
        foreach ($journals as $jid => $jInfo) {
            $journalStats[$jid] = [
                'id' => $jid,
                'path' => $jInfo['path'],
                'name' => $jInfo['name'],
                'acronym' => $jInfo['acronym'],
                'total' => 0,
                'normal' => 0,
                'almostDue' => 0,
                'overdue' => 0,
                'noTarget' => 0,
                'normalPct' => 0,
                'almostDuePct' => 0,
                'overduePct' => 0,
                'noTargetPct' => 0,
            ];
        }

        foreach ($allItems as $item) {
            $jid = (int) $item['contextId'];
            if (!isset($journalStats[$jid])) {
                continue;
            }
            $siteSummary['total']++;
            $journalStats[$jid]['total']++;

            if ($item['status'] === 'normal') {
                $siteSummary['normal']++;
                $journalStats[$jid]['normal']++;
            } elseif ($item['status'] === 'almost_due') {
                $siteSummary['almostDue']++;
                $journalStats[$jid]['almostDue']++;
            } elseif ($item['status'] === 'overdue') {
                $siteSummary['overdue']++;
                $journalStats[$jid]['overdue']++;
            } elseif ($item['status'] === 'no_target') {
                $siteSummary['noTarget']++;
                $journalStats[$jid]['noTarget']++;
            }
        }

        foreach ($journalStats as $jid => &$st) {
            if ($st['total'] > 0) {
                $st['normalPct'] = round(($st['normal'] / $st['total']) * 100, 1);
                $st['almostDuePct'] = round(($st['almostDue'] / $st['total']) * 100, 1);
                $st['overduePct'] = round(($st['overdue'] / $st['total']) * 100, 1);
                $st['noTargetPct'] = round(($st['noTarget'] / $st['total']) * 100, 1);
            }
        }
        unset($st);

        // Sort journal rows
        $sortBy = isset($filters['sort']) ? (string) $filters['sort'] : 'most_overdue';
        $journalRows = array_values($journalStats);
        usort($journalRows, function ($a, $b) use ($sortBy) {
            if ($sortBy === 'most_almost_due') {
                if ($b['almostDue'] !== $a['almostDue']) {
                    return $b['almostDue'] - $a['almostDue'];
                }
                return $b['overdue'] - $a['overdue'];
            } elseif ($sortBy === 'most_articles') {
                if ($b['total'] !== $a['total']) {
                    return $b['total'] - $a['total'];
                }
                return $b['overdue'] - $a['overdue'];
            } elseif ($sortBy === 'name_asc') {
                return strcasecmp($a['name'], $b['name']);
            }
            // Default: most_overdue
            if ($b['overdue'] !== $a['overdue']) {
                return $b['overdue'] - $a['overdue'];
            }
            if ($b['almostDue'] !== $a['almostDue']) {
                return $b['almostDue'] - $a['almostDue'];
            }
            return $b['total'] - $a['total'];
        });

        // Most urgent articles across all journals
        $urgentCandidates = array_filter($allItems, function ($item) {
            return in_array($item['status'], ['overdue', 'almost_due'], true);
        });
        $urgentItems = $this->filterAndSortItems(array_values($urgentCandidates), ['sort' => 'urgency']);
        $urgentTop = array_slice($urgentItems, 0, 25);

        // Sort allItems by urgency for CSV detail export
        $sortedAllItems = $this->filterAndSortItems($allItems, ['sort' => 'urgency']);

        return [
            'summary' => $siteSummary,
            'journalRows' => $journalRows,
            'urgentItems' => $urgentTop,
            'allItems' => $sortedAllItems,
        ];
    }

    /**
     * Filter and sort processed dashboard items.
     *
     * @param array $items
     * @param array $filters
     * @return array
     */
    public function filterAndSortItems($items, $filters = [])
    {
        $stage = isset($filters['stage']) ? trim((string) $filters['stage']) : 'all';
        $status = isset($filters['status']) ? trim((string) $filters['status']) : 'all';
        $editorId = isset($filters['editorId']) && $filters['editorId'] !== '' && $filters['editorId'] !== 'all' ? (int) $filters['editorId'] : null;
        $issueId = isset($filters['issueId']) ? trim((string) $filters['issueId']) : 'all';
        $search = isset($filters['search']) ? strtolower(trim((string) $filters['search'])) : '';
        $sort = isset($filters['sort']) ? trim((string) $filters['sort']) : 'urgency';

        $filtered = array_filter($items, function ($item) use ($stage, $status, $editorId, $issueId, $search) {
            if ($stage !== 'all' && $stage !== '' && $item['stageKey'] !== $stage) {
                return false;
            }
            if ($status !== 'all' && $status !== '' && $item['status'] !== $status) {
                return false;
            }
            if ($editorId !== null) {
                if ($editorId === 0) {
                    if (!empty($item['editors'])) {
                        return false;
                    }
                } else {
                    $matchedEditor = false;
                    foreach ($item['editors'] as $ed) {
                        if ((int) $ed['userId'] === $editorId) {
                            $matchedEditor = true;
                            break;
                        }
                    }
                    if (!$matchedEditor) {
                        return false;
                    }
                }
            }
            if ($issueId !== 'all' && $issueId !== '') {
                if ($issueId === 'none') {
                    if (!empty($item['targetIssueId'])) {
                        return false;
                    }
                } elseif ((int) $item['targetIssueId'] !== (int) $issueId) {
                    return false;
                }
            }
            if ($search !== '') {
                $haystack = strtolower($item['id'] . ' ' . $item['title'] . ' ' . $item['editorNames']);
                if (strpos($haystack, $search) === false) {
                    return false;
                }
            }
            return true;
        });

        $result = array_values($filtered);

        usort($result, function ($a, $b) use ($sort) {
            if ($sort === 'id_asc') {
                return $a['id'] - $b['id'];
            } elseif ($sort === 'id_desc') {
                return $b['id'] - $a['id'];
            } elseif ($sort === 'due_asc') {
                $da = $a['dueDate'] ?: '9999-12-31';
                $db = $b['dueDate'] ?: '9999-12-31';
                return strcmp($da, $db);
            } elseif ($sort === 'start_asc') {
                $sa = $a['startDate'] ?: '9999-12-31';
                $sb = $b['startDate'] ?: '9999-12-31';
                return strcmp($sa, $sb);
            }

            // Default: Most urgent first (overdue longest at top, then almost due, then no target, then normal)
            if ($b['urgencyScore'] !== $a['urgencyScore']) {
                return $b['urgencyScore'] - $a['urgencyScore'];
            }
            return $a['id'] - $b['id'];
        });

        return $result;
    }

    /**
     * Process raw database batch into enriched submission items.
     *
     * @param array $batch
     * @param array<int, array> $settingsByJournal
     * @param array<int, WorkingDayCalculator> $calculatorsByJournal
     * @param array<int, array> $issuesMap
     * @param bool $canViewReviewerIdentity
     * @param string $locale
     * @param array $journalsMap
     * @return array
     */
    protected function _processSubmissionsBatch($batch, $settingsByJournal, $calculatorsByJournal, $issuesMap, $canViewReviewerIdentity = true, $locale = 'en_US', $journalsMap = [])
    {
        if (empty($batch['submissions'])) {
            return [];
        }

        $items = [];
        $userNames = isset($batch['userNames']) ? $batch['userNames'] : [];

        foreach ($batch['submissions'] as $subRow) {
            $subId = (int) $subRow->submission_id;
            $ctxId = (int) $subRow->context_id;
            $stageId = (int) $subRow->stage_id;

            $settings = isset($settingsByJournal[$ctxId]) ? $settingsByJournal[$ctxId] : $this->getContextSettings($ctxId);
            $calculator = isset($calculatorsByJournal[$ctxId]) ? $calculatorsByJournal[$ctxId] : new WorkingDayCalculator([], $settings['timezone'], $settings['workingDays']);

            $title = isset($batch['titles'][$subId]) && $batch['titles'][$subId] !== '' ? $batch['titles'][$subId] : __('common.untitled');

            $editors = isset($batch['editors'][$subId]) ? array_values($batch['editors'][$subId]) : [];
            $editorNameList = array_column($editors, 'fullName');
            $editorNames = !empty($editorNameList) ? implode(', ', $editorNameList) : __('plugins.generic.editorialReminderDashboard.unassignedEditor');

            $decisions = isset($batch['editDecisions'][$subId]) ? $batch['editDecisions'][$subId] : [];
            $rounds = isset($batch['reviewRounds'][$subId]) ? $batch['reviewRounds'][$subId] : [];
            $assignments = isset($batch['reviewAssignments'][$subId]) ? $batch['reviewAssignments'][$subId] : [];

            // Build Review Round History across all review rounds
            $roundHistoryData = $this->_computeRoundHistory($subRow, $rounds, $assignments, $decisions, $calculator, $settings);

            $journalName = isset($journalsMap[$ctxId]['name']) ? $journalsMap[$ctxId]['name'] : '';
            $journalPath = isset($journalsMap[$ctxId]['path']) ? $journalsMap[$ctxId]['path'] : '';

            if ($stageId === 2 || $stageId === 3) {
                // Exclude if final decision (Accept=1, Decline=4, Initial Decline=9, Send to Production=7) has been taken on current stage
                if ($this->_hasFinalReviewDecision($decisions, $stageId, $roundHistoryData['currentRoundNumber'])) {
                    continue;
                }

                $currentRoundNum = $roundHistoryData['currentRoundNumber'];
                $startDate = $roundHistoryData['currentRoundStartDate'];
                $pauseEndDate = $roundHistoryData['currentRoundPauseDate']; // Pause if waiting for author revisions

                $limitDays = $currentRoundNum <= 1 ? $settings['round1Limit'] : $settings['round2Limit'];
                $warnDays = $currentRoundNum <= 1 ? $settings['round1Warning'] : $settings['round2Warning'];

                $eval = $calculator->evaluateReviewRound($startDate, $limitDays, $warnDays, $pauseEndDate);

                // Reviewer breakdown for active round
                $activeRoundReviewers = $this->_buildRoundReviewers($assignments, $currentRoundNum, $stageId, $userNames, $canViewReviewerIdentity);

                // Delay cause
                $delayCauseInfo = $this->_determineReviewDelayCause($activeRoundReviewers, $decisions, $currentRoundNum, $stageId, $pauseEndDate !== null);

                $timeRemainingText = $this->_formatReviewTimeRemaining($eval, $pauseEndDate !== null);

                $items[] = [
                    'id' => $subId,
                    'contextId' => $ctxId,
                    'journalName' => $journalName,
                    'journalPath' => $journalPath,
                    'title' => $title,
                    'stageId' => $stageId,
                    'stageKey' => 'review',
                    'stageLabel' => __('plugins.generic.editorialReminderDashboard.stage.reviewRound', ['round' => $currentRoundNum]),
                    'round' => $currentRoundNum,
                    'editors' => $editors,
                    'editorNames' => $editorNames,
                    'startDate' => $eval['startDate'],
                    'startDateFormatted' => $this->formatShortDate($eval['startDate'], $locale),
                    'dueDate' => $eval['dueDate'],
                    'dueDateFormatted' => $this->formatShortDate($eval['dueDate'], $locale),
                    'elapsedWorkingDays' => $eval['elapsedWorkingDays'],
                    'remainingWorkingDays' => $eval['remainingWorkingDays'],
                    'overdueWorkingDays' => $eval['overdueWorkingDays'],
                    'limitWorkingDays' => $limitDays,
                    'timeRemainingText' => $timeRemainingText,
                    'status' => $eval['status'],
                    'statusLabel' => $this->getStatusLabel($eval['status']),
                    'statusSymbol' => $this->getStatusSymbol($eval['status']),
                    'delayCauseKey' => $delayCauseInfo['key'],
                    'delayCauseLabel' => $delayCauseInfo['label'],
                    'isPausedForAuthorRevision' => $pauseEndDate !== null,
                    'urgencyScore' => $eval['urgencyScore'],
                    'reviewers' => $activeRoundReviewers,
                    'roundHistory' => $roundHistoryData['historyList'],
                    'cumulativeWorkingDays' => $roundHistoryData['cumulativeWorkingDays'] + $eval['elapsedWorkingDays'],
                    'targetIssueId' => null,
                    'targetIssueLabel' => null,
                    'targetIssueLogs' => [],
                ];
            } elseif ($stageId === 4) {
                // Copyediting stage
                $targetIssueObj = isset($batch['targetIssues'][$subId]) ? $batch['targetIssues'][$subId] : null;
                $targetIssueId = $targetIssueObj && !empty($targetIssueObj->issue_id) ? (int) $targetIssueObj->issue_id : (isset($batch['publicationIssues'][$subId]) ? (int) $batch['publicationIssues'][$subId] : null);

                $targetDate = null;
                $targetIssueShort = null;
                $targetIssueFull = null;

                if ($targetIssueId && isset($issuesMap[$targetIssueId])) {
                    $issueMeta = $issuesMap[$targetIssueId];
                    $targetDate = $issueMeta['targetDate'];
                    $targetIssueShort = $issueMeta['shortLabel'];
                    $targetIssueFull = $issueMeta['identification'];
                } elseif ($targetIssueObj && !empty($targetIssueObj->target_date)) {
                    $targetDate = substr((string) $targetIssueObj->target_date, 0, 10);
                }

                $copyEval = $calculator->evaluateCopyediting($targetDate, $settings['copyeditingWarning']);
                $stageLabel = $targetIssueShort ? __('plugins.generic.editorialReminderDashboard.stage.copyeditingWithIssue', ['issue' => $targetIssueShort]) : __('plugins.generic.editorialReminderDashboard.stage.copyediting');

                $timeRemainingText = $this->_formatCopyeditingTimeRemaining($copyEval);

                // Format target issue logs
                $rawLogs = isset($batch['targetIssueLogs'][$subId]) ? $batch['targetIssueLogs'][$subId] : [];
                $formattedLogs = [];
                foreach ($rawLogs as $lg) {
                    $oldId = !empty($lg->old_issue_id) ? (int) $lg->old_issue_id : null;
                    $newId = !empty($lg->new_issue_id) ? (int) $lg->new_issue_id : null;
                    $changerId = (int) $lg->changed_by;
                    $formattedLogs[] = [
                        'dateChanged' => (string) $lg->date_changed,
                        'changedByName' => isset($userNames[$changerId]) ? $userNames[$changerId] : 'User #' . $changerId,
                        'oldIssueLabel' => $oldId && isset($issuesMap[$oldId]) ? $issuesMap[$oldId]['identification'] : __('common.none'),
                        'newIssueLabel' => $newId && isset($issuesMap[$newId]) ? $issuesMap[$newId]['identification'] : __('common.none'),
                    ];
                }

                $delayCauseLabel = '';
                if ($copyEval['status'] === 'no_target') {
                    $delayCauseLabel = __('plugins.generic.editorialReminderDashboard.delayCause.noTargetIssue');
                } elseif ($copyEval['status'] === 'overdue' || $copyEval['status'] === 'almost_due') {
                    $delayCauseLabel = __('plugins.generic.editorialReminderDashboard.delayCause.copyeditingTarget');
                }

                $items[] = [
                    'id' => $subId,
                    'contextId' => $ctxId,
                    'journalName' => $journalName,
                    'journalPath' => $journalPath,
                    'title' => $title,
                    'stageId' => $stageId,
                    'stageKey' => 'copyediting',
                    'stageLabel' => $stageLabel,
                    'round' => null,
                    'editors' => $editors,
                    'editorNames' => $editorNames,
                    'startDate' => null,
                    'startDateFormatted' => '—',
                    'dueDate' => $copyEval['targetDate'],
                    'dueDateFormatted' => $copyEval['targetDate'] ? $this->formatShortDate($copyEval['targetDate'], $locale) : '—',
                    'elapsedWorkingDays' => null,
                    'remainingWorkingDays' => $copyEval['remainingCalendarDays'],
                    'overdueWorkingDays' => $copyEval['overdueCalendarDays'],
                    'limitWorkingDays' => null,
                    'timeRemainingText' => $timeRemainingText,
                    'status' => $copyEval['status'],
                    'statusLabel' => $this->getStatusLabel($copyEval['status']),
                    'statusSymbol' => $this->getStatusSymbol($copyEval['status']),
                    'delayCauseKey' => $copyEval['status'] === 'no_target' ? 'no_target_issue' : 'copyediting_schedule',
                    'delayCauseLabel' => $delayCauseLabel,
                    'isPausedForAuthorRevision' => false,
                    'urgencyScore' => $copyEval['urgencyScore'],
                    'reviewers' => [],
                    'roundHistory' => $roundHistoryData['historyList'],
                    'cumulativeWorkingDays' => $roundHistoryData['cumulativeWorkingDays'],
                    'targetIssueId' => $targetIssueId,
                    'targetIssueLabel' => $targetIssueFull,
                    'targetIssueShortLabel' => $targetIssueShort,
                    'targetIssueLogs' => $formattedLogs,
                ];
            }
        }

        return $items;
    }

    /**
     * Compute start date, end date, and duration for each review round of a submission.
     *
     * @param object $subRow
     * @param array $rounds
     * @param array $assignments
     * @param array $decisions
     * @param WorkingDayCalculator $calculator
     * @param array $settings
     * @return array
     */
    protected function _computeRoundHistory($subRow, $rounds, $assignments, $decisions, $calculator, $settings)
    {
        $subId = (int) $subRow->submission_id;
        $ctxId = (int) $subRow->context_id;

        if (empty($rounds)) {
            // Fallback if submission is in Review stage but review_rounds row hasn't been created yet
            $fallbackStart = $this->_findRoundStartDate($subRow, 1, 3, $decisions, $assignments);
            return [
                'currentRoundNumber' => 1,
                'currentRoundStartDate' => $fallbackStart,
                'currentRoundPauseDate' => null,
                'historyList' => [],
                'cumulativeWorkingDays' => 0,
            ];
        }

        $totalRounds = count($rounds);
        $historyList = [];
        $cumulativeWorkingDays = 0;
        $currentRoundNumber = 1;
        $currentRoundStartDate = substr((string) ($subRow->date_submitted ?: date('Y-m-d')), 0, 10);
        $currentRoundPauseDate = null;

        foreach ($rounds as $idx => $roundObj) {
            $roundNum = (int) $roundObj->round;
            $rStageId = (int) ($roundObj->stage_id ?: 3);
            $startDate = $this->_findRoundStartDate($subRow, $roundNum, $rStageId, $decisions, $assignments);

            $isLastRound = $idx === $totalRounds - 1 && ((int) $subRow->stage_id === 2 || (int) $subRow->stage_id === 3);

            if (!$isLastRound) {
                // Completed round: find when the next round started or when the round decision was taken
                $nextRoundObj = isset($rounds[$idx + 1]) ? $rounds[$idx + 1] : null;
                $endDate = null;
                if ($nextRoundObj) {
                    $endDate = $this->_findRoundStartDate($subRow, (int) $nextRoundObj->round, (int) ($nextRoundObj->stage_id ?: 3), $decisions, $assignments);
                }
                // Also check if there was a Request Revisions / Accept / Decline decision on this round before next round
                $roundDecisionDate = $this->_findRoundEndDecisionDate($roundNum, $rStageId, $decisions);
                if ($roundDecisionDate && (!$endDate || $roundDecisionDate < $endDate)) {
                    $endDate = $roundDecisionDate;
                }
                if (!$endDate) {
                    $endDate = substr((string) ($subRow->date_last_activity ?: date('Y-m-d')), 0, 10);
                }

                $limit = $roundNum <= 1 ? $settings['round1Limit'] : $settings['round2Limit'];
                $durationWorkingDays = $calculator->countWorkingDaysBetween($startDate, $endDate);
                $finalStatus = $durationWorkingDays > $limit ? 'overdue' : 'completed';

                $this->_dao->upsertRoundHistory($subId, $ctxId, (int) $roundObj->review_round_id, $rStageId, $roundNum, $startDate, $endDate, $durationWorkingDays, $finalStatus);

                $cumulativeWorkingDays += $durationWorkingDays;
                $historyList[] = [
                    'round' => $roundNum,
                    'dateStart' => $startDate,
                    'dateEnd' => $endDate,
                    'workingDays' => $durationWorkingDays,
                    'limitWorkingDays' => $limit,
                    'finalStatus' => $finalStatus,
                    'finalStatusLabel' => $finalStatus === 'overdue' ? __('plugins.generic.editorialReminderDashboard.roundHistory.completedOverdue', ['days' => $durationWorkingDays]) : __('plugins.generic.editorialReminderDashboard.roundHistory.completedOnTime', ['days' => $durationWorkingDays]),
                ];
            } else {
                $currentRoundNumber = $roundNum;
                $currentRoundStartDate = $startDate;
                // Check if active round is currently waiting for author revisions
                $currentRoundPauseDate = $this->_findActiveRevisionRequestDate($roundObj, $decisions);
            }
        }

        return [
            'currentRoundNumber' => $currentRoundNumber,
            'currentRoundStartDate' => $currentRoundStartDate,
            'currentRoundPauseDate' => $currentRoundPauseDate,
            'historyList' => $historyList,
            'cumulativeWorkingDays' => $cumulativeWorkingDays,
        ];
    }

    /**
     * Determine the start date of a specific review round.
     * Priority:
     * 1. Editor decision "Send to Review" (8 or 3) for Round 1, or "New Review Round" (16) for Round 2+.
     * 2. Fallback: Earliest reviewer `date_assigned` in that round.
     * 3. Fallback: `date_submitted` (Round 1) or `date_last_activity`.
     *
     * @param object $subRow
     * @param int $roundNum
     * @param int $stageId
     * @param array $decisions
     * @param array $assignments
     * @return string 'Y-m-d'
     */
    protected function _findRoundStartDate($subRow, $roundNum, $stageId, $decisions, $assignments)
    {
        $decisionDate = null;

        if ($roundNum <= 1) {
            // Look for Send to External/Internal Review decision (8 = SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW, 3 = SEND_TO_EXTERNAL)
            foreach ($decisions as $d) {
                $decCode = (int) $d->decision;
                if (in_array($decCode, [8, 3], true) && !empty($d->date_decided)) {
                    $decisionDate = substr((string) $d->date_decided, 0, 10);
                    break;
                }
            }
        } else {
            // Look for New Review Round decision (16) on round $roundNum or $roundNum - 1
            foreach ($decisions as $d) {
                $decCode = (int) $d->decision;
                $dRound = (int) $d->round;
                if ($decCode === 16 && ($dRound === $roundNum || $dRound === $roundNum - 1) && !empty($d->date_decided)) {
                    $decisionDate = substr((string) $d->date_decided, 0, 10);
                }
            }
        }

        if ($decisionDate) {
            return $decisionDate;
        }

        // Fallback: First reviewer assigned date on that round
        $earliestAssign = null;
        foreach ($assignments as $ra) {
            if ((int) $ra->round === $roundNum && !empty($ra->date_assigned)) {
                $ymd = substr((string) $ra->date_assigned, 0, 10);
                if ($earliestAssign === null || $ymd < $earliestAssign) {
                    $earliestAssign = $ymd;
                }
            }
        }

        if ($earliestAssign) {
            return $earliestAssign;
        }

        // Final fallback
        if ($roundNum <= 1 && !empty($subRow->date_submitted)) {
            return substr((string) $subRow->date_submitted, 0, 10);
        }
        if (!empty($subRow->date_last_activity)) {
            return substr((string) $subRow->date_last_activity, 0, 10);
        }

        return date('Y-m-d');
    }

    /**
     * Find the date an editor took a round-ending decision (Revisions=2, Resubmit=3, Accept=1, Decline=4) on a historical round.
     *
     * @param int $roundNum
     * @param int $stageId
     * @param array $decisions
     * @return string|null 'Y-m-d'
     */
    protected function _findRoundEndDecisionDate($roundNum, $stageId, $decisions)
    {
        $found = null;
        foreach ($decisions as $d) {
            if ((int) $d->round === $roundNum && in_array((int) $d->decision, [1, 2, 3, 4, 16], true) && !empty($d->date_decided)) {
                $found = substr((string) $d->date_decided, 0, 10);
                break;
            }
        }
        return $found;
    }

    /**
     * Check if the current active review round is paused waiting for author revisions.
     *
     * @param object $roundObj
     * @param array $decisions
     * @return string|null Pause date 'Y-m-d' if waiting for author revisions, or null
     */
    protected function _findActiveRevisionRequestDate($roundObj, $decisions)
    {
        $roundStatus = (int) $roundObj->status;
        // REVIEW_ROUND_STATUS_REVISIONS_REQUESTED (1), REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW (2)
        if (!in_array($roundStatus, [1, 2], true)) {
            return null;
        }

        $roundNum = (int) $roundObj->round;
        $latestRevDecisionDate = null;
        foreach ($decisions as $d) {
            if ((int) $d->round === $roundNum && in_array((int) $d->decision, [2, 3], true) && !empty($d->date_decided)) {
                $latestRevDecisionDate = substr((string) $d->date_decided, 0, 10);
            }
        }

        return $latestRevDecisionDate;
    }

    /**
     * Check whether a final decision (Accept=1, Decline=4, Initial Decline=9) has already been made.
     *
     * @param array $decisions
     * @param int $stageId
     * @param int $roundNum
     * @return bool
     */
    protected function _hasFinalReviewDecision($decisions, $stageId, $roundNum)
    {
        foreach ($decisions as $d) {
            $dec = (int) $d->decision;
            if (in_array($dec, [1, 4, 9], true) && (int) $d->round >= $roundNum) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build detailed reviewer list for the active review round.
     *
     * @param array $assignments
     * @param int $roundNum
     * @param int $stageId
     * @param array $userNames
     * @param bool $canViewReviewerIdentity
     * @return array
     */
    protected function _buildRoundReviewers($assignments, $roundNum, $stageId, $userNames, $canViewReviewerIdentity = true)
    {
        $list = [];
        $todayYmd = date('Y-m-d');

        foreach ($assignments as $ra) {
            if ((int) $ra->round !== $roundNum) {
                continue;
            }

            $reviewerId = (int) $ra->reviewer_id;
            $fullName = $canViewReviewerIdentity ? (isset($userNames[$reviewerId]) ? $userNames[$reviewerId] : (string) $ra->reviewer_username) : __('plugins.generic.editorialReminderDashboard.reviewer.anonymous');

            $dateAssigned = !empty($ra->date_assigned) ? substr((string) $ra->date_assigned, 0, 10) : null;
            $dateConfirmed = !empty($ra->date_confirmed) ? substr((string) $ra->date_confirmed, 0, 10) : null;
            $dateCompleted = !empty($ra->date_completed) ? substr((string) $ra->date_completed, 0, 10) : null;
            $dateResponseDue = !empty($ra->date_response_due) ? substr((string) $ra->date_response_due, 0, 10) : null;
            $dateDue = !empty($ra->date_due) ? substr((string) $ra->date_due, 0, 10) : null;
            $declined = (int) $ra->declined === 1;
            $cancelled = (int) $ra->cancelled === 1;

            $isOjsOverdue = false;
            if ($cancelled) {
                $statusKey = 'cancelled';
                $statusLabel = __('plugins.generic.editorialReminderDashboard.reviewerStatus.cancelled');
            } elseif ($declined) {
                $statusKey = 'declined';
                $statusLabel = __('plugins.generic.editorialReminderDashboard.reviewerStatus.declined');
            } elseif ($dateCompleted !== null) {
                $statusKey = 'completed';
                $statusLabel = __('plugins.generic.editorialReminderDashboard.reviewerStatus.completed');
            } elseif ($dateConfirmed !== null) {
                $statusKey = 'accepted';
                $statusLabel = __('plugins.generic.editorialReminderDashboard.reviewerStatus.accepted');
                if ($dateDue && $dateDue < $todayYmd) {
                    $isOjsOverdue = true;
                }
            } else {
                $statusKey = 'awaiting_response';
                $statusLabel = __('plugins.generic.editorialReminderDashboard.reviewerStatus.awaitingResponse');
                if (($dateResponseDue && $dateResponseDue < $todayYmd) || ($dateDue && $dateDue < $todayYmd)) {
                    $isOjsOverdue = true;
                }
            }

            $list[] = [
                'reviewId' => (int) $ra->review_id,
                'reviewerId' => $reviewerId,
                'reviewerName' => $fullName,
                'dateAssigned' => $dateAssigned ?: '—',
                'dateResponseDue' => $dateResponseDue ?: '—',
                'dateDue' => $dateDue ?: '—',
                'dateCompleted' => $dateCompleted ?: '—',
                'statusKey' => $statusKey,
                'statusLabel' => $statusLabel,
                'isOjsOverdue' => $isOjsOverdue,
                'isActive' => !$cancelled && !$declined,
                'isCompleted' => $dateCompleted !== null && !$cancelled && !$declined,
            ];
        }

        return $list;
    }

    /**
     * Determine the cause of delay for a review round.
     * - Waiting for reviewers: at least one active reviewer has not completed their review.
     * - Waiting for editor decision: all active reviewers have completed (with >= 1 completed review) and no decision yet.
     * - No reviewers assigned: 0 active reviewers (and 0 completed reviews).
     *
     * @param array $reviewers
     * @param array $decisions
     * @param int $roundNum
     * @param int $stageId
     * @param bool $isPausedForRevision
     * @return array {key: string, label: string}
     */
    protected function _determineReviewDelayCause($reviewers, $decisions, $roundNum, $stageId, $isPausedForRevision = false)
    {
        if ($isPausedForRevision) {
            return [
                'key' => 'waiting_author_revision',
                'label' => __('plugins.generic.editorialReminderDashboard.delayCause.waitingAuthorRevision'),
            ];
        }

        $activeCount = 0;
        $completedCount = 0;
        $incompleteActiveCount = 0;

        foreach ($reviewers as $rev) {
            if ($rev['isActive']) {
                $activeCount++;
                if ($rev['isCompleted']) {
                    $completedCount++;
                } else {
                    $incompleteActiveCount++;
                }
            }
        }

        if ($activeCount === 0 || ($incompleteActiveCount === 0 && $completedCount === 0)) {
            return [
                'key' => 'no_reviewer_assigned',
                'label' => __('plugins.generic.editorialReminderDashboard.delayCause.noReviewerAssigned'),
            ];
        }

        if ($incompleteActiveCount > 0) {
            return [
                'key' => 'waiting_reviewer',
                'label' => __('plugins.generic.editorialReminderDashboard.delayCause.waitingReviewer'),
            ];
        }

        return [
            'key' => 'waiting_editor_decision',
            'label' => __('plugins.generic.editorialReminderDashboard.delayCause.waitingEditorDecision'),
        ];
    }

    /**
     * Format human-readable remaining/overdue working days text for Review stage.
     *
     * @param array $eval
     * @param bool $isPaused
     * @return string
     */
    protected function _formatReviewTimeRemaining($eval, $isPaused = false)
    {
        if ($eval['status'] === 'overdue') {
            return __('plugins.generic.editorialReminderDashboard.time.overdueWorkingDays', ['days' => $eval['overdueWorkingDays']]);
        }
        if ($eval['remainingWorkingDays'] === 0) {
            return __('plugins.generic.editorialReminderDashboard.time.dueToday');
        }
        return __('plugins.generic.editorialReminderDashboard.time.remainingWorkingDays', ['days' => $eval['remainingWorkingDays']]);
    }

    /**
     * Format human-readable remaining/overdue calendar days text for Copyediting stage.
     *
     * @param array $copyEval
     * @return string
     */
    protected function _formatCopyeditingTimeRemaining($copyEval)
    {
        if ($copyEval['status'] === 'no_target') {
            return __('plugins.generic.editorialReminderDashboard.status.noTarget');
        }
        if ($copyEval['status'] === 'overdue') {
            return __('plugins.generic.editorialReminderDashboard.time.overdueTargetDay', ['days' => $copyEval['overdueCalendarDays']]);
        }
        if (!empty($copyEval['isDueToday'])) {
            return __('plugins.generic.editorialReminderDashboard.time.dueToday');
        }
        return __('plugins.generic.editorialReminderDashboard.time.remainingCalendarDays', ['days' => $copyEval['remainingCalendarDays']]);
    }

    /**
     * Get localized status label.
     *
     * @param string $status
     * @return string
     */
    public function getStatusLabel($status)
    {
        switch ($status) {
            case 'overdue':
                return __('plugins.generic.editorialReminderDashboard.status.overdue');
            case 'almost_due':
                return __('plugins.generic.editorialReminderDashboard.status.almostDue');
            case 'no_target':
                return __('plugins.generic.editorialReminderDashboard.status.noTarget');
            case 'normal':
            default:
                return __('plugins.generic.editorialReminderDashboard.status.normal');
        }
    }

    /**
     * Get accessible status symbol (Section 6.5: ● green, ▲ yellow, ■ red, ◆ gray).
     *
     * @param string $status
     * @return string
     */
    public function getStatusSymbol($status)
    {
        switch ($status) {
            case 'overdue':
                return '■';
            case 'almost_due':
                return '▲';
            case 'no_target':
                return '◆';
            case 'normal':
            default:
                return '●';
        }
    }

    /**
     * Format a 'Y-m-d' date into a concise localized date like "28 Oct" or "6 Nov".
     *
     * @param string|null $ymd
     * @param string $locale
     * @return string
     */
    public function formatShortDate($ymd, $locale = 'en_US')
    {
        if (empty($ymd) || $ymd === '—') {
            return '—';
        }
        $ts = strtotime(substr((string) $ymd, 0, 10));
        if ($ts === false) {
            return (string) $ymd;
        }

        $day = (int) date('j', $ts);
        $monthNum = (int) date('n', $ts);

        $monthKey = 'plugins.generic.editorialReminderDashboard.month.short.' . $monthNum;
        $mName = __($monthKey, [], $locale);
        if ($mName === $monthKey) {
            $mName = date('M', $ts);
        }

        return $day . ' ' . $mName;
    }
}
