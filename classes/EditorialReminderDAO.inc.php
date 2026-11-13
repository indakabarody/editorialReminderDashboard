<?php
/**
 * @file plugins/generic/editorialReminderDashboard/classes/EditorialReminderDAO.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderDAO
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Data Access Object for reading OJS editorial data in batches and managing
 * custom Editorial Reminder plugin tables without altering core OJS tables.
 */

import('lib.pkp.classes.db.DAO');

use Illuminate\Database\Capsule\Manager as Capsule;

class EditorialReminderDAO extends DAO
{
    /** @var bool Whether schema check has run in this request */
    protected static $_schemaChecked = false;

    /**
     * Ensure plugin tables exist so the plugin operates safely even if enabled without CLI upgrade.
     *
     * @return void
     */
    public function ensureSchema()
    {
        if (self::$_schemaChecked) {
            return;
        }
        self::$_schemaChecked = true;

        try {
            if (!Capsule::schema()->hasTable('editorial_reminder_target_issues')) {
                require_once dirname(__DIR__) . '/EditorialReminderSchemaMigration.inc.php';
                $migration = new EditorialReminderSchemaMigration();
                $migration->up();
            }
        } catch (Exception $e) {
            error_log('EditorialReminderDashboard schema check warning: ' . $e->getMessage());
        }
    }

    /**
     * Get all enabled journals (contexts) on this OJS installation.
     *
     * @param string $locale Current locale
     * @return array List of journal associative arrays
     */
    public function getEnabledJournals($locale = 'en_US')
    {
        $rows = Capsule::table('journals')->orderBy('seq', 'asc')->orderBy('journal_id', 'asc')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $journalIds = [];
        foreach ($rows as $row) {
            $journalIds[] = (int) $row->journal_id;
        }

        $settingsRows = Capsule::table('journal_settings')
            ->whereIn('journal_id', $journalIds)
            ->whereIn('setting_name', ['name', 'acronym', 'contactEmail'])
            ->get();

        $settingsByJournal = [];
        foreach ($settingsRows as $s) {
            $jid = (int) $s->journal_id;
            $loc = (string) $s->locale;
            $settingsByJournal[$jid][$s->setting_name][$loc] = $s->setting_value;
        }

        $journals = [];
        foreach ($rows as $row) {
            $jid = (int) $row->journal_id;
            $primaryLocale = $row->primary_locale ?: 'en_US';
            $nameMap = isset($settingsByJournal[$jid]['name']) ? $settingsByJournal[$jid]['name'] : [];
            $acronymMap = isset($settingsByJournal[$jid]['acronym']) ? $settingsByJournal[$jid]['acronym'] : [];

            $name = $this->_pickLocalizedValue($nameMap, $locale, $primaryLocale, $row->path);
            $acronym = $this->_pickLocalizedValue($acronymMap, $locale, $primaryLocale, strtoupper($row->path));

            $journals[$jid] = [
                'id' => $jid,
                'path' => (string) $row->path,
                'enabled' => (int) $row->enabled,
                'primaryLocale' => $primaryLocale,
                'name' => $name,
                'acronym' => $acronym,
            ];
        }

        return $journals;
    }

    /**
     * Fetch active monitored submissions (Review & Copyediting stages) for one or more journals.
     * Only reads queued (active) submissions that have completed initial submission progress.
     *
     * @param int|array $contextIds Journal ID or array of Journal IDs
     * @param int|null $assignedEditorId Optional user ID filter for Section Editor
     * @param string $locale Current UI locale
     * @return array Batch data structure containing submissions and related records
     */
    public function getMonitoredSubmissionsBatch($contextIds, $assignedEditorId = null, $locale = 'en_US')
    {
        $this->ensureSchema();

        $contextIdList = is_array($contextIds) ? array_map('intval', $contextIds) : [(int) $contextIds];
        if (empty($contextIdList)) {
            return ['submissions' => []];
        }

        // Stages monitored: Internal Review (2), External Review (3), Copyediting (4)
        // Status: STATUS_QUEUED (1), submission_progress = 0
        $query = Capsule::table('submissions as s')
            ->select(['s.submission_id', 's.context_id', 's.current_publication_id', 's.date_submitted', 's.date_last_activity', 's.last_modified', 's.stage_id', 's.status', 's.locale as submission_locale'])
            ->whereIn('s.context_id', $contextIdList)
            ->where('s.status', '=', 1) // STATUS_QUEUED
            ->where('s.submission_progress', '=', 0)
            ->whereIn('s.stage_id', [2, 3, 4]);

        if ($assignedEditorId !== null) {
            $editorUserId = (int) $assignedEditorId;
            $query->whereExists(function ($subQuery) use ($editorUserId) {
                $subQuery
                    ->select(Capsule::raw(1))
                    ->from('stage_assignments as sa')
                    ->join('user_groups as ug', 'sa.user_group_id', '=', 'ug.user_group_id')
                    ->whereRaw('sa.submission_id = s.submission_id')
                    ->where('sa.user_id', '=', $editorUserId)
                    ->whereIn('ug.role_id', [16, 17]); // ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR
            });
        }

        $submissionRows = $query->orderBy('s.submission_id', 'asc')->get();
        if ($submissionRows->isEmpty()) {
            return [
                'submissions' => [],
                'titles' => [],
                'publicationIssues' => [],
                'editors' => [],
                'reviewRounds' => [],
                'reviewAssignments' => [],
                'editDecisions' => [],
                'targetIssues' => [],
                'targetIssueLogs' => [],
                'roundHistory' => [],
            ];
        }

        $submissionIds = [];
        $publicationIds = [];
        foreach ($submissionRows as $row) {
            $submissionIds[] = (int) $row->submission_id;
            if (!empty($row->current_publication_id)) {
                $publicationIds[] = (int) $row->current_publication_id;
            }
        }

        // 1. Titles & Issue IDs from publication_settings (and fallback submission_settings)
        $titlesBySubmission = [];
        $pubIssueBySubmission = [];
        if (!empty($publicationIds)) {
            $pubRows = Capsule::table('publications')->whereIn('publication_id', $publicationIds)->get();
            $pubToSub = [];
            foreach ($pubRows as $p) {
                $pubToSub[(int) $p->publication_id] = (int) $p->submission_id;
            }

            $pubSettings = Capsule::table('publication_settings')
                ->whereIn('publication_id', $publicationIds)
                ->whereIn('setting_name', ['title', 'fullTitle', 'prefix', 'subtitle', 'issueId'])
                ->get();

            $rawTitles = [];
            foreach ($pubSettings as $ps) {
                $pubId = (int) $ps->publication_id;
                if (!isset($pubToSub[$pubId])) {
                    continue;
                }
                $subId = $pubToSub[$pubId];
                if ($ps->setting_name === 'issueId' && !empty($ps->setting_value)) {
                    $pubIssueBySubmission[$subId] = (int) $ps->setting_value;
                } else {
                    $rawTitles[$subId][$ps->setting_name][(string) $ps->locale] = $ps->setting_value;
                }
            }

            foreach ($submissionRows as $row) {
                $subId = (int) $row->submission_id;
                $subLoc = $row->submission_locale ?: $locale;
                $fullTitleMap = isset($rawTitles[$subId]['fullTitle']) ? $rawTitles[$subId]['fullTitle'] : [];
                $titleMap = isset($rawTitles[$subId]['title']) ? $rawTitles[$subId]['title'] : [];
                $title = $this->_pickLocalizedValue($fullTitleMap, $locale, $subLoc, '');
                if ($title === '') {
                    $title = $this->_pickLocalizedValue($titleMap, $locale, $subLoc, '');
                }
                $titlesBySubmission[$subId] = strip_tags($title);
            }
        }

        // 2. Stage assignments (Assigned Editors & Section Editors: role_id 16 = Manager/Editor, 17 = Sub Editor)
        $editorRows = Capsule::table('stage_assignments as sa')
            ->join('user_groups as ug', 'sa.user_group_id', '=', 'ug.user_group_id')
            ->join('users as u', 'sa.user_id', '=', 'u.user_id')
            ->select(['sa.submission_id', 'sa.user_id', 'sa.user_group_id', 'sa.date_assigned', 'sa.recommend_only', 'ug.role_id', 'u.username'])
            ->whereIn('sa.submission_id', $submissionIds)
            ->whereIn('ug.role_id', [16, 17])
            ->orderBy('ug.role_id', 'desc') // Prefer Section Editor (17) then Editor/Manager (16)
            ->orderBy('sa.stage_assignment_id', 'asc')
            ->get();

        // Collect all user IDs (editors + reviewers) to batch-fetch full names from user_settings
        $userIds = [];
        foreach ($editorRows as $er) {
            $userIds[(int) $er->user_id] = true;
        }

        // 3. Review Rounds
        $roundRows = Capsule::table('review_rounds')->whereIn('submission_id', $submissionIds)->orderBy('stage_id', 'asc')->orderBy('round', 'asc')->get();

        $reviewRoundsBySub = [];
        foreach ($roundRows as $rr) {
            $reviewRoundsBySub[(int) $rr->submission_id][] = $rr;
        }

        // 4. Review Assignments
        $reviewAssignmentRows = Capsule::table('review_assignments as ra')
            ->leftJoin('users as u', 'ra.reviewer_id', '=', 'u.user_id')
            ->select(['ra.*', 'u.username as reviewer_username'])
            ->whereIn('ra.submission_id', $submissionIds)
            ->orderBy('ra.round', 'asc')
            ->orderBy('ra.review_id', 'asc')
            ->get();

        $reviewAssignmentsBySub = [];
        foreach ($reviewAssignmentRows as $ra) {
            $reviewAssignmentsBySub[(int) $ra->submission_id][] = $ra;
            if (!empty($ra->reviewer_id)) {
                $userIds[(int) $ra->reviewer_id] = true;
            }
        }

        // 5. Editor Decisions
        $decisionRows = Capsule::table('edit_decisions')->whereIn('submission_id', $submissionIds)->orderBy('date_decided', 'asc')->orderBy('edit_decision_id', 'asc')->get();

        $decisionsBySub = [];
        foreach ($decisionRows as $ed) {
            $decisionsBySub[(int) $ed->submission_id][] = $ed;
            if (!empty($ed->editor_id)) {
                $userIds[(int) $ed->editor_id] = true;
            }
        }

        // 6. Plugin Target Issues
        $targetIssueRows = Capsule::table('editorial_reminder_target_issues')->whereIn('submission_id', $submissionIds)->get();

        $targetIssuesBySub = [];
        foreach ($targetIssueRows as $ti) {
            $targetIssuesBySub[(int) $ti->submission_id] = $ti;
            if (!empty($ti->updated_by)) {
                $userIds[(int) $ti->updated_by] = true;
            }
        }

        // 7. Target Issue Change Logs
        $targetLogRows = Capsule::table('editorial_reminder_target_issue_logs')->whereIn('submission_id', $submissionIds)->orderBy('date_changed', 'desc')->get();

        $targetLogsBySub = [];
        foreach ($targetLogRows as $tl) {
            $targetLogsBySub[(int) $tl->submission_id][] = $tl;
            if (!empty($tl->changed_by)) {
                $userIds[(int) $tl->changed_by] = true;
            }
        }

        // 8. Stored Round History
        $historyRows = Capsule::table('editorial_reminder_round_history')->whereIn('submission_id', $submissionIds)->orderBy('round', 'asc')->get();

        $roundHistoryBySub = [];
        foreach ($historyRows as $hr) {
            $roundHistoryBySub[(int) $hr->submission_id][(int) $hr->round] = $hr;
        }

        // Batch fetch user full names from user_settings
        $userNames = $this->getUserFullNames(array_keys($userIds), $locale);

        $editorsBySub = [];
        foreach ($editorRows as $er) {
            $subId = (int) $er->submission_id;
            $uid = (int) $er->user_id;
            $editorsBySub[$subId][$uid] = [
                'userId' => $uid,
                'roleId' => (int) $er->role_id,
                'username' => (string) $er->username,
                'fullName' => isset($userNames[$uid]) ? $userNames[$uid] : (string) $er->username,
                'recommendOnly' => (bool) $er->recommend_only,
            ];
        }

        return [
            'submissions' => $submissionRows,
            'titles' => $titlesBySubmission,
            'publicationIssues' => $pubIssueBySubmission,
            'editors' => $editorsBySub,
            'reviewRounds' => $reviewRoundsBySub,
            'reviewAssignments' => $reviewAssignmentsBySub,
            'editDecisions' => $decisionsBySub,
            'targetIssues' => $targetIssuesBySub,
            'targetIssueLogs' => $targetLogsBySub,
            'roundHistory' => $roundHistoryBySub,
            'userNames' => $userNames,
        ];
    }

    /**
     * Batch-resolve user full names from `user_settings` (`givenName`, `familyName`).
     *
     * @param array $userIds Array of user IDs
     * @param string $locale Current locale
     * @return array<int, string> Map of userId => fullName
     */
    public function getUserFullNames($userIds, $locale = 'en_US')
    {
        if (empty($userIds)) {
            return [];
        }

        $cleanIds = array_values(array_unique(array_map('intval', $userIds)));
        $userRows = Capsule::table('users')
            ->select(['user_id', 'username'])
            ->whereIn('user_id', $cleanIds)
            ->get();

        $usernames = [];
        foreach ($userRows as $u) {
            $usernames[(int) $u->user_id] = (string) $u->username;
        }

        $settingRows = Capsule::table('user_settings')
            ->whereIn('user_id', $cleanIds)
            ->whereIn('setting_name', ['givenName', 'familyName', 'preferredPublicName'])
            ->get();

        $byUser = [];
        foreach ($settingRows as $sr) {
            $uid = (int) $sr->user_id;
            $byUser[$uid][$sr->setting_name][(string) $sr->locale] = $sr->setting_value;
        }

        $names = [];
        foreach ($cleanIds as $uid) {
            $prefMap = isset($byUser[$uid]['preferredPublicName']) ? $byUser[$uid]['preferredPublicName'] : [];
            $givenMap = isset($byUser[$uid]['givenName']) ? $byUser[$uid]['givenName'] : [];
            $familyMap = isset($byUser[$uid]['familyName']) ? $byUser[$uid]['familyName'] : [];

            $pref = trim($this->_pickLocalizedValue($prefMap, $locale, 'en_US', ''));
            if ($pref !== '') {
                $names[$uid] = $pref;
                continue;
            }

            $given = trim($this->_pickLocalizedValue($givenMap, $locale, 'en_US', ''));
            $family = trim($this->_pickLocalizedValue($familyMap, $locale, 'en_US', ''));
            $combined = trim($given . ' ' . $family);
            $names[$uid] = $combined !== '' ? $combined : (isset($usernames[$uid]) ? $usernames[$uid] : 'User #' . $uid);
        }

        return $names;
    }

    /**
     * Fetch unpublished issues (and any referenced target issues) for the given context(s),
     * along with their stored or default Target Publication Date H.
     *
     * @param int|array $contextIds Journal ID(s)
     * @param string $locale Current locale
     * @return array<int, array> Map of issueId => issue metadata including targetDate
     */
    public function getIssuesWithTargetDates($contextIds, $locale = 'en_US')
    {
        $this->ensureSchema();

        $contextIdList = is_array($contextIds) ? array_map('intval', $contextIds) : [(int) $contextIds];
        if (empty($contextIdList)) {
            return [];
        }

        $issueRows = Capsule::table('issues')->whereIn('journal_id', $contextIdList)->orderBy('published', 'asc')->orderBy('year', 'desc')->orderBy('volume', 'desc')->orderBy('number', 'desc')->orderBy('issue_id', 'desc')->get();

        if ($issueRows->isEmpty()) {
            return [];
        }

        $issueIds = [];
        foreach ($issueRows as $ir) {
            $issueIds[] = (int) $ir->issue_id;
        }

        $settingRows = Capsule::table('issue_settings')
            ->whereIn('issue_id', $issueIds)
            ->whereIn('setting_name', ['title', 'description'])
            ->get();

        $titlesByIssue = [];
        foreach ($settingRows as $sr) {
            if ($sr->setting_name === 'title') {
                $titlesByIssue[(int) $sr->issue_id][(string) $sr->locale] = $sr->setting_value;
            }
        }

        $targetRows = Capsule::table('editorial_reminder_issue_targets')->whereIn('issue_id', $issueIds)->get();

        $customTargets = [];
        foreach ($targetRows as $tr) {
            $customTargets[(int) $tr->issue_id] = $tr;
        }

        $issues = [];
        foreach ($issueRows as $ir) {
            $issueId = (int) $ir->issue_id;
            $titleMap = isset($titlesByIssue[$issueId]) ? $titlesByIssue[$issueId] : [];
            $title = trim($this->_pickLocalizedValue($titleMap, $locale, 'en_US', ''));

            // Build human-readable identification (e.g., "Vol 4 No 3 (2026): November")
            $parts = [];
            if (!empty($ir->volume)) {
                $parts[] = 'Vol ' . $ir->volume;
            }
            if ($ir->number !== null && $ir->number !== '') {
                $parts[] = 'No ' . $ir->number;
            }
            $volNum = implode(' ', $parts);
            $shortLabel = $volNum !== '' ? $volNum : ($title !== '' ? $title : 'Issue #' . $issueId);

            $fullLabel = $volNum;
            if (!empty($ir->year)) {
                $fullLabel .= ($fullLabel !== '' ? ' ' : '') . '(' . $ir->year . ')';
            }
            if ($title !== '') {
                $fullLabel .= ($fullLabel !== '' ? ': ' : '') . $title;
            }
            if ($fullLabel === '') {
                $fullLabel = 'Issue #' . $issueId;
            }

            $defaultTargetDate = $this->inferDefaultIssueTargetDate($ir, $title);
            $storedTargetDate = isset($customTargets[$issueId]) && !empty($customTargets[$issueId]->target_date) ? substr((string) $customTargets[$issueId]->target_date, 0, 10) : null;

            $issues[$issueId] = [
                'id' => $issueId,
                'contextId' => (int) $ir->journal_id,
                'volume' => $ir->volume,
                'number' => $ir->number,
                'year' => $ir->year,
                'published' => (int) $ir->published,
                'title' => $title,
                'shortLabel' => $shortLabel,
                'identification' => $fullLabel,
                'targetDate' => $storedTargetDate ?: $defaultTargetDate,
                'isCustomTargetDate' => $storedTargetDate !== null,
                'defaultTargetDate' => $defaultTargetDate,
            ];
        }

        return $issues;
    }

    /**
     * Infer the default target publication date (H) as the last day of the issue's month.
     * Checks issue title for month names (Indonesian & English), `date_published`, or issue year.
     *
     * @param object $issueRow Row from `issues` table
     * @param string $title Localized issue title
     * @return string Date string in 'Y-m-d' format
     */
    public function inferDefaultIssueTargetDate($issueRow, $title = '')
    {
        $year = !empty($issueRow->year) ? (int) $issueRow->year : (int) date('Y');
        if ($year < 1970 || $year > 2100) {
            $year = (int) date('Y');
        }

        $monthMap = [
            'januari' => 1,
            'january' => 1,
            'jan' => 1,
            'februari' => 2,
            'february' => 2,
            'feb' => 2,
            'maret' => 3,
            'march' => 3,
            'mar' => 3,
            'april' => 4,
            'apr' => 4,
            'mei' => 5,
            'may' => 5,
            'juni' => 6,
            'june' => 6,
            'jun' => 6,
            'juli' => 7,
            'july' => 7,
            'jul' => 7,
            'agustus' => 8,
            'august' => 8,
            'agu' => 8,
            'aug' => 8,
            'september' => 9,
            'sep' => 9,
            'sept' => 9,
            'oktober' => 10,
            'october' => 10,
            'okt' => 10,
            'oct' => 10,
            'november' => 11,
            'nov' => 11,
            'desember' => 12,
            'december' => 12,
            'des' => 12,
            'dec' => 12,
        ];

        $lowerTitle = strtolower((string) $title);
        $detectedMonth = null;
        foreach ($monthMap as $keyword => $m) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $lowerTitle)) {
                $detectedMonth = $m;
                break;
            }
        }

        if ($detectedMonth === null && !empty($issueRow->date_published)) {
            $ts = strtotime((string) $issueRow->date_published);
            if ($ts !== false) {
                return date('Y-m-t', $ts);
            }
        }

        if ($detectedMonth === null) {
            $detectedMonth = (int) date('n');
        }

        $firstOfMonth = sprintf('%04d-%02d-01', $year, $detectedMonth);
        $ts = strtotime($firstOfMonth);
        return $ts !== false ? date('Y-m-t', $ts) : date('Y-m-t');
    }

    /**
     * Assign or update Target Issue for a submission in Copyediting and log the change.
     *
     * @param int $submissionId
     * @param int $contextId
     * @param int|null $issueId Null to clear target issue
     * @param string|null $targetDate
     * @param int $userId User performing the action
     * @return void
     */
    public function setSubmissionTargetIssue($submissionId, $contextId, $issueId, $targetDate, $userId)
    {
        $this->ensureSchema();

        $subId = (int) $submissionId;
        $ctxId = (int) $contextId;
        $newIssueId = !empty($issueId) ? (int) $issueId : null;
        $uid = (int) $userId;
        $now = date('Y-m-d H:i:s');

        $existing = Capsule::table('editorial_reminder_target_issues')->where('submission_id', '=', $subId)->first();

        $oldIssueId = $existing && !empty($existing->issue_id) ? (int) $existing->issue_id : null;

        if ($existing) {
            Capsule::table('editorial_reminder_target_issues')
                ->where('submission_id', '=', $subId)
                ->update([
                    'context_id' => $ctxId,
                    'issue_id' => $newIssueId,
                    'target_date' => $targetDate ? substr($targetDate, 0, 10) : null,
                    'updated_by' => $uid,
                    'date_updated' => $now,
                ]);
        } else {
            Capsule::table('editorial_reminder_target_issues')->insert([
                'submission_id' => $subId,
                'context_id' => $ctxId,
                'issue_id' => $newIssueId,
                'target_date' => $targetDate ? substr($targetDate, 0, 10) : null,
                'updated_by' => $uid,
                'date_updated' => $now,
            ]);
        }

        if ($oldIssueId !== $newIssueId) {
            Capsule::table('editorial_reminder_target_issue_logs')->insert([
                'submission_id' => $subId,
                'context_id' => $ctxId,
                'old_issue_id' => $oldIssueId,
                'new_issue_id' => $newIssueId,
                'changed_by' => $uid,
                'date_changed' => $now,
            ]);

            $this->logAudit(
                $ctxId,
                $uid,
                'target_issue_changed',
                json_encode([
                    'submissionId' => $subId,
                    'oldIssueId' => $oldIssueId,
                    'newIssueId' => $newIssueId,
                    'targetDate' => $targetDate,
                ]),
            );
        }
    }

    /**
     * Set custom target publication date H for an issue.
     *
     * @param int $issueId
     * @param int $contextId
     * @param string $targetDate 'Y-m-d'
     * @param int $userId
     * @return void
     */
    public function setIssueTargetDate($issueId, $contextId, $targetDate, $userId)
    {
        $this->ensureSchema();

        $issId = (int) $issueId;
        $ctxId = (int) $contextId;
        $ymd = substr(trim((string) $targetDate), 0, 10);
        $uid = (int) $userId;
        $now = date('Y-m-d H:i:s');

        $existing = Capsule::table('editorial_reminder_issue_targets')->where('issue_id', '=', $issId)->first();

        if ($existing) {
            Capsule::table('editorial_reminder_issue_targets')
                ->where('issue_id', '=', $issId)
                ->update([
                    'context_id' => $ctxId,
                    'target_date' => $ymd,
                    'updated_by' => $uid,
                    'date_updated' => $now,
                ]);
        } else {
            Capsule::table('editorial_reminder_issue_targets')->insert([
                'issue_id' => $issId,
                'context_id' => $ctxId,
                'target_date' => $ymd,
                'updated_by' => $uid,
                'date_updated' => $now,
            ]);
        }

        // Sync target_date on submissions assigned to this issue
        Capsule::table('editorial_reminder_target_issues')
            ->where('issue_id', '=', $issId)
            ->update(['target_date' => $ymd]);

        $this->logAudit($ctxId, $uid, 'issue_target_date_updated', json_encode(['issueId' => $issId, 'targetDate' => $ymd]));
    }

    /**
     * Get holidays applicable to a journal (combines site-wide holidays context_id=0 and journal holidays).
     *
     * @param int|null $contextId Journal ID or 0 for site-wide only
     * @return array List of holiday records
     */
    public function getHolidays($contextId = null)
    {
        $this->ensureSchema();

        $query = Capsule::table('editorial_reminder_holidays')->orderBy('holiday_date', 'asc');

        if ($contextId === null || (int) $contextId === 0) {
            $query->where('context_id', '=', 0);
        } else {
            $query->whereIn('context_id', [0, (int) $contextId]);
        }

        $rows = $query->get();
        $holidays = [];
        foreach ($rows as $row) {
            $holidays[] = [
                'id' => (int) $row->holiday_id,
                'contextId' => (int) $row->context_id,
                'date' => substr((string) $row->holiday_date, 0, 10),
                'description' => (string) $row->description,
            ];
        }

        return $holidays;
    }

    /**
     * Add or update a holiday date.
     *
     * @param int $contextId 0 for global, or journal ID
     * @param string $holidayDate 'Y-m-d'
     * @param string $description Holiday name/description
     * @param int $userId
     * @return int Holiday ID
     */
    public function addHoliday($contextId, $holidayDate, $description, $userId)
    {
        $this->ensureSchema();

        $ctxId = (int) $contextId;
        $ymd = substr(trim((string) $holidayDate), 0, 10);
        $desc = trim((string) $description);
        $now = date('Y-m-d H:i:s');

        $existing = Capsule::table('editorial_reminder_holidays')->where('context_id', '=', $ctxId)->where('holiday_date', '=', $ymd)->first();

        if ($existing) {
            Capsule::table('editorial_reminder_holidays')
                ->where('holiday_id', '=', (int) $existing->holiday_id)
                ->update(['description' => $desc]);
            $holidayId = (int) $existing->holiday_id;
        } else {
            $holidayId = (int) Capsule::table('editorial_reminder_holidays')->insertGetId(
                [
                    'context_id' => $ctxId,
                    'holiday_date' => $ymd,
                    'description' => $desc,
                    'created_at' => $now,
                ],
                'holiday_id',
            );
        }

        $this->logAudit($ctxId, (int) $userId, 'holiday_saved', json_encode(['date' => $ymd, 'description' => $desc]));

        return $holidayId;
    }

    /**
     * Delete a holiday entry by ID.
     *
     * @param int $holidayId
     * @param int $contextId
     * @param int $userId
     * @return bool
     */
    public function deleteHoliday($holidayId, $contextId, $userId)
    {
        $this->ensureSchema();

        $query = Capsule::table('editorial_reminder_holidays')->where('holiday_id', '=', (int) $holidayId);

        if ((int) $contextId > 0) {
            $query->where('context_id', '=', (int) $contextId);
        }

        $deleted = $query->delete();
        if ($deleted) {
            $this->logAudit((int) $contextId, (int) $userId, 'holiday_deleted', json_encode(['holidayId' => (int) $holidayId]));
        }
        return (bool) $deleted;
    }

    /**
     * Persist completed review round snapshot into `editorial_reminder_round_history`.
     *
     * @param int $submissionId
     * @param int $contextId
     * @param int|null $reviewRoundId
     * @param int $stageId
     * @param int $round
     * @param string $dateStart 'Y-m-d'
     * @param string|null $dateEnd 'Y-m-d'
     * @param int $workingDays
     * @param string $finalStatus
     * @return void
     */
    public function upsertRoundHistory($submissionId, $contextId, $reviewRoundId, $stageId, $round, $dateStart, $dateEnd, $workingDays, $finalStatus)
    {
        $this->ensureSchema();

        $subId = (int) $submissionId;
        $stgId = (int) $stageId;
        $rnd = (int) $round;

        $existing = Capsule::table('editorial_reminder_round_history')->where('submission_id', '=', $subId)->where('stage_id', '=', $stgId)->where('round', '=', $rnd)->first();

        $payload = [
            'context_id' => (int) $contextId,
            'review_round_id' => $reviewRoundId ? (int) $reviewRoundId : null,
            'date_start' => substr((string) $dateStart, 0, 10),
            'date_end' => $dateEnd ? substr((string) $dateEnd, 0, 10) : null,
            'working_days' => (int) $workingDays,
            'final_status' => (string) $finalStatus,
        ];

        if ($existing) {
            if ($existing->date_end !== $payload['date_end'] || (int) $existing->working_days !== $payload['working_days']) {
                Capsule::table('editorial_reminder_round_history')->where('history_id', '=', (int) $existing->history_id)->update($payload);
            }
        } else {
            $payload['submission_id'] = $subId;
            $payload['stage_id'] = $stgId;
            $payload['round'] = $rnd;
            Capsule::table('editorial_reminder_round_history')->insert($payload);
        }
    }

    /**
     * Record an audit log entry (Section 10 Audit Trail).
     *
     * @param int $contextId
     * @param int $userId
     * @param string $action
     * @param string|null $details
     * @return void
     */
    public function logAudit($contextId, $userId, $action, $details = null)
    {
        $this->ensureSchema();

        try {
            Capsule::table('editorial_reminder_audit_logs')->insert([
                'context_id' => (int) $contextId,
                'user_id' => (int) $userId,
                'action' => substr((string) $action, 0, 100),
                'details' => $details,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // Do not block editorial operations if audit write fails
        }
    }

    /**
     * Fetch recent audit log entries for a context.
     *
     * @param int $contextId
     * @param int $limit
     * @param string $locale
     * @return array
     */
    public function getRecentAuditLogs($contextId, $limit = 20, $locale = 'en_US')
    {
        $this->ensureSchema();

        $query = Capsule::table('editorial_reminder_audit_logs')->orderBy('audit_id', 'desc')->limit((int) $limit);

        if ((int) $contextId > 0) {
            $query->whereIn('context_id', [0, (int) $contextId]);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $uids = [];
        foreach ($rows as $r) {
            $uids[] = (int) $r->user_id;
        }
        $userNames = $this->getUserFullNames($uids, $locale);

        $logs = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            $logs[] = [
                'id' => (int) $r->audit_id,
                'contextId' => (int) $r->context_id,
                'userId' => $uid,
                'userName' => isset($userNames[$uid]) ? $userNames[$uid] : 'User #' . $uid,
                'action' => (string) $r->action,
                'details' => (string) $r->details,
                'createdAt' => (string) $r->created_at,
            ];
        }

        return $logs;
    }

    /**
     * Check whether a user is a Site Administrator, Journal Manager, Journal Editor, or Section Editor
     * in the given context.
     *
     * Note on OJS 3.3 architecture:
     * In OJS 3.3 (`registry/userGroups.xml`), Journal Manager (`default.groups.name.manager`),
     * Journal Editor (`default.groups.name.editor`), and Production Editor (`default.groups.name.productionEditor`)
     * all share `role_id = 16` (`ROLE_ID_MANAGER`). To distinguish true Journal Managers from
     * Journal Editors and Production Editors, we inspect `user_group_settings` (`nameLocaleKey`,
     * `abbrevLocaleKey`, `name`, and `abbrev`).
     *
     * @param int $userId
     * @param int $contextId
     * @return array {isSiteAdmin: bool, isManager: bool, isJournalEditor: bool, isSubEditor: bool}
     */
    public function getUserContextRoleFlags($userId, $contextId)
    {
        $uid = (int) $userId;
        $ctxId = (int) $contextId;

        $rows = Capsule::table('user_user_groups as uug')
            ->join('user_groups as ug', 'uug.user_group_id', '=', 'ug.user_group_id')
            ->select(['ug.user_group_id', 'ug.role_id', 'ug.context_id'])
            ->where('uug.user_id', '=', $uid)
            ->whereIn('ug.context_id', [0, $ctxId])
            ->get();

        $isSiteAdmin = false;
        $isManager = false;
        $isJournalEditor = false;
        $isSubEditor = false;

        $managerRoleGroupIds = [];

        foreach ($rows as $r) {
            $roleId = (int) $r->role_id;
            if ($roleId === 1) {
                // ROLE_ID_SITE_ADMIN
                $isSiteAdmin = true;
            } elseif ($roleId === 16 && (int) $r->context_id === $ctxId) {
                // ROLE_ID_MANAGER (shared by Journal Manager, Journal Editor, and Production Editor in OJS 3.3)
                $managerRoleGroupIds[] = (int) $r->user_group_id;
            } elseif ($roleId === 17 && (int) $r->context_id === $ctxId) {
                // ROLE_ID_SUB_EDITOR
                $isSubEditor = true;
            }
        }

        if (!empty($managerRoleGroupIds)) {
            $jmGroupIds = $this->filterJournalManagerUserGroupIds($managerRoleGroupIds);
            foreach ($managerRoleGroupIds as $ugId) {
                if (in_array($ugId, $jmGroupIds, true)) {
                    $isManager = true;
                } else {
                    $isJournalEditor = true;
                }
            }
        }

        return [
            'isSiteAdmin' => $isSiteAdmin,
            'isManager' => $isManager,
            'isJournalEditor' => $isJournalEditor,
            'isSubEditor' => $isSubEditor,
        ];
    }

    /**
     * Filter a list of `role_id = 16` (`ROLE_ID_MANAGER`) user group IDs and return only those
     * that represent the actual Journal Manager user group (excluding Journal Editor and Production Editor).
     *
     * @param array $userGroupIds Array of `user_group_id` integers with `role_id = 16`
     * @return array<int> Filtered list of `user_group_id` integers that are Journal Manager groups
     */
    public function filterJournalManagerUserGroupIds($userGroupIds)
    {
        if (empty($userGroupIds)) {
            return [];
        }

        $cleanIds = array_values(array_unique(array_map('intval', $userGroupIds)));
        $settingRows = Capsule::table('user_group_settings')
            ->whereIn('user_group_id', $cleanIds)
            ->whereIn('setting_name', ['nameLocaleKey', 'abbrevLocaleKey', 'name', 'abbrev'])
            ->get();

        $settingsByGroup = [];
        foreach ($settingRows as $sr) {
            $ugId = (int) $sr->user_group_id;
            $sName = (string) $sr->setting_name;
            $sVal = trim((string) $sr->setting_value);
            if ($sName === 'nameLocaleKey' || $sName === 'abbrevLocaleKey') {
                $settingsByGroup[$ugId][$sName] = $sVal;
            } else {
                $settingsByGroup[$ugId][$sName][] = strtolower($sVal);
            }
        }

        $jmGroupIds = [];
        foreach ($cleanIds as $ugId) {
            $gSettings = isset($settingsByGroup[$ugId]) ? $settingsByGroup[$ugId] : [];
            $nameLocaleKey = isset($gSettings['nameLocaleKey']) ? $gSettings['nameLocaleKey'] : '';
            $abbrevLocaleKey = isset($gSettings['abbrevLocaleKey']) ? $gSettings['abbrevLocaleKey'] : '';

            // 1. Standard OJS 3.3 user groups installed from registry/userGroups.xml
            if ($nameLocaleKey !== '' || $abbrevLocaleKey !== '') {
                if ($nameLocaleKey === 'default.groups.name.manager' || $abbrevLocaleKey === 'default.groups.abbrev.manager') {
                    $jmGroupIds[] = $ugId;
                }
                continue;
            }

            // 2. Fallback for custom role_id=16 groups created without nameLocaleKey
            $names = isset($gSettings['name']) ? $gSettings['name'] : [];
            $abbrevs = isset($gSettings['abbrev']) ? $gSettings['abbrev'] : [];

            $hasEditorKeyword = false;
            $hasManagerKeyword = false;

            foreach ($names as $n) {
                if (strpos($n, 'editor') !== false || strpos($n, 'redaktur') !== false || strpos($n, 'penyunting') !== false) {
                    $hasEditorKeyword = true;
                }
                if (strpos($n, 'journal manager') !== false || strpos($n, 'manajer jurnal') !== false || strpos($n, 'pengelola jurnal') !== false || $n === 'manager' || $n === 'manajer') {
                    $hasManagerKeyword = true;
                }
            }

            if (!$hasManagerKeyword) {
                foreach ($abbrevs as $ab) {
                    if ($ab === 'jm' || $ab === 'mj') {
                        $hasManagerKeyword = true;
                    }
                    if ($ab === 'je' || $ab === 'ej' || $ab === 'prode' || $ab === 'ep') {
                        $hasEditorKeyword = true;
                    }
                }
            }

            if ($hasManagerKeyword && !$hasEditorKeyword) {
                $jmGroupIds[] = $ugId;
            }
        }

        return $jmGroupIds;
    }

    /**
     * Helper to pick a localized string with sensible fallbacks.
     *
     * @param array $map Map of locale => value
     * @param string $preferredLocale
     * @param string $fallbackLocale
     * @param string $default
     * @return string
     */
    protected function _pickLocalizedValue($map, $preferredLocale, $fallbackLocale = 'en_US', $default = '')
    {
        if (!empty($map[$preferredLocale])) {
            return (string) $map[$preferredLocale];
        }
        if (!empty($map[$fallbackLocale])) {
            return (string) $map[$fallbackLocale];
        }
        if (!empty($map[''])) {
            return (string) $map[''];
        }
        foreach ($map as $val) {
            if (!empty($val)) {
                return (string) $val;
            }
        }
        return (string) $default;
    }
}
