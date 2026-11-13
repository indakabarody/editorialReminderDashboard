<?php
/**
 * @file plugins/generic/editorialReminderDashboard/classes/tasks/EditorialReminderDigestTask.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderDigestTask
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Scheduled task to send daily digest emails to Editors/Section Editors
 * and weekly digest emails to Journal Managers and Directors, respecting user opt-out.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');

use Illuminate\Database\Capsule\Manager as Capsule;

class EditorialReminderDigestTask extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('plugins.generic.editorialReminderDashboard.digest.taskName');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions()
    {
        $plugin = PluginRegistry::getPlugin('generic', 'editorialreminderdashboardplugin');
        if (!$plugin) {
            $plugin = PluginRegistry::loadPlugin('generic', 'editorialReminderDashboard');
        }
        if (!$plugin) {
            return false;
        }

        $plugin->addLocaleData();
        require_once dirname(__DIR__) . '/EditorialReminderService.inc.php';
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();

        $journals = $dao->getEnabledJournals('id_ID');
        $isMonday = (int) date('N') === 1;

        import('lib.pkp.classes.mail.Mail');

        foreach ($journals as $jid => $jInfo) {
            if (!$plugin->getEnabled($jid) && !$plugin->getEnabled(0)) {
                continue;
            }

            $settings = $service->getContextSettings($jid);
            $data = $service->getJournalDashboardData($jid, [], null, true, $jInfo['primaryLocale']);
            $urgentItems = array_filter($data['items'], function ($it) {
                return in_array($it['status'], ['overdue', 'almost_due'], true);
            });

            if (empty($urgentItems)) {
                continue;
            }

            // Daily digest to assigned Editors / Section Editors
            if (!empty($settings['enableDailyDigest'])) {
                $byEditor = [];
                foreach ($urgentItems as $item) {
                    foreach ($item['editors'] as $ed) {
                        $byEditor[(int) $ed['userId']][] = $item;
                    }
                }

                foreach ($byEditor as $editorUserId => $editorItems) {
                    if ($this->_isUserOptedOut($plugin, $editorUserId)) {
                        continue;
                    }
                    $userRow = Capsule::table('users')->where('user_id', '=', $editorUserId)->first();
                    if (!$userRow || empty($userRow->email)) {
                        continue;
                    }
                    $dailySubject = __('plugins.generic.editorialReminderDashboard.digest.dailySubject', ['journal' => $jInfo['name']]);
                    $this->_sendDigestMail($userRow->email, $jInfo['name'], $dailySubject, $editorItems);
                }
            }

            // Weekly digest on Mondays to Journal Managers
            if ($isMonday && !empty($settings['enableWeeklyDigest'])) {
                $managerGroupRows = Capsule::table('user_groups')
                    ->select(['user_group_id'])
                    ->where('context_id', '=', $jid)
                    ->where('role_id', '=', 16)
                    ->get();

                $candidateGroupIds = [];
                foreach ($managerGroupRows as $mgrRow) {
                    $candidateGroupIds[] = (int) $mgrRow->user_group_id;
                }
                $jmGroupIds = $dao->filterJournalManagerUserGroupIds($candidateGroupIds);

                $managerRows = !empty($jmGroupIds)
                    ? Capsule::table('user_user_groups as uug')
                        ->join('users as u', 'uug.user_id', '=', 'u.user_id')
                        ->select(['u.user_id', 'u.email'])
                        ->whereIn('uug.user_group_id', $jmGroupIds)
                        ->get()
                    : [];

                $sentEmails = [];
                foreach ($managerRows as $mr) {
                    $uid = (int) $mr->user_id;
                    if (isset($sentEmails[$mr->email]) || $this->_isUserOptedOut($plugin, $uid)) {
                        continue;
                    }
                    $sentEmails[$mr->email] = true;
                    $weeklySubject = __('plugins.generic.editorialReminderDashboard.digest.weeklySubject', ['journal' => $jInfo['name']]);
                    $this->_sendDigestMail($mr->email, $jInfo['name'], $weeklySubject, $urgentItems);
                }
            }
        }

        return true;
    }

    /**
     * Check if user opted out of Editorial Reminder email notifications.
     *
     * @param Plugin $plugin
     * @param int $userId
     * @return bool
     */
    protected function _isUserOptedOut($plugin, $userId)
    {
        $optOut = $plugin->getSetting(0, 'optOutUser_' . (int) $userId);
        return !empty($optOut);
    }

    /**
     * Send plain-text digest email summarizing overdue and almost-due articles.
     *
     * @param string $toEmail
     * @param string $journalName
     * @param string $subject
     * @param array $items
     * @return void
     */
    protected function _sendDigestMail($toEmail, $journalName, $subject, $items)
    {
        $lines = [];
        $lines[] = $subject;
        $lines[] = str_repeat('=', strlen($subject));
        $lines[] = '';
        $lines[] = __('plugins.generic.editorialReminderDashboard.digest.journalLabel') . ' ' . $journalName;
        $lines[] = __('plugins.generic.editorialReminderDashboard.digest.dateLabel') . ' ' . date('Y-m-d H:i');
        $lines[] = __('plugins.generic.editorialReminderDashboard.digest.actionRequiredCount') . ' ' . count($items);
        $lines[] = '';

        foreach ($items as $it) {
            $lines[] = sprintf('- [#%d] %s | %s | Due: %s | %s (%s) | %s', $it['id'], $it['title'], $it['stageLabel'], $it['dueDateFormatted'], $it['statusLabel'], $it['timeRemainingText'], $it['delayCauseLabel'] ?: '-');
        }

        try {
            $mail = new Mail();
            $mail->setRecipients([['name' => '', 'email' => $toEmail]]);
            $mail->setSubject($subject);
            $mail->setBody(implode("\n", $lines));
            $mail->send();
        } catch (Exception $e) {
            error_log('EditorialReminderDigestTask mail error: ' . $e->getMessage());
        }
    }
}
