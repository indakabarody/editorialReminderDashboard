<?php
/**
 * @file plugins/generic/editorialReminderDashboard/EditorialReminderSettingsForm.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderSettingsForm
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Form for Journal Managers and Site Administrators to modify Editorial Reminder settings.
 */

import('lib.pkp.classes.form.Form');

require_once __DIR__ . '/classes/EditorialReminderService.inc.php';

class EditorialReminderSettingsForm extends Form
{
    /** @var EditorialReminderDashboardPlugin */
    public $plugin;

    /** @var int */
    public $contextId;

    /**
     * Constructor.
     *
     * @param EditorialReminderDashboardPlugin $plugin
     * @param int|null $contextId
     */
    public function __construct($plugin, $contextId = null)
    {
        parent::__construct($plugin->getTemplateResource('settings.tpl'));
        $this->plugin = $plugin;

        if ($contextId === null) {
            $context = Application::get()->getRequest()->getContext();
            $this->contextId = $context ? (int) $context->getId() : 0;
        } else {
            $this->contextId = (int) $contextId;
        }

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $service = new EditorialReminderService($this->plugin);
        $settings = $service->getContextSettings($this->contextId);

        $this->setData('round1Limit', $settings['round1Limit']);
        $this->setData('round2Limit', $settings['round2Limit']);
        $this->setData('round1Warning', $settings['round1Warning']);
        $this->setData('round2Warning', $settings['round2Warning']);
        $this->setData('copyeditingWarning', $settings['copyeditingWarning']);
        $this->setData('timezone', $settings['timezone']);
        $this->setData('directorUsernames', $settings['directorUsernames']);
        $this->setData('enableDailyDigest', $settings['enableDailyDigest']);
        $this->setData('enableWeeklyDigest', $settings['enableWeeklyDigest']);

        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['round1Limit', 'round2Limit', 'round1Warning', 'round2Warning', 'copyeditingWarning', 'timezone', 'directorUsernames', 'enableDailyDigest', 'enableWeeklyDigest']);
        parent::readInputData();
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        $dashboardUrl = $context ? $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder') : $dispatcher->url($request, ROUTE_PAGE, 'index', 'editorialReminder', 'site');

        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'isSiteAdmin' => Validation::isSiteAdmin(),
            'contextId' => $this->contextId,
            'dashboardUrl' => $dashboardUrl,
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $ctxId = $this->contextId;

        $round1Limit = max(14, min(21, (int) $this->getData('round1Limit') ?: 21));
        $round2Limit = max(1, (int) $this->getData('round2Limit') ?: 7);
        $round1Warning = max(1, (int) $this->getData('round1Warning') ?: 3);
        $round2Warning = max(1, (int) $this->getData('round2Warning') ?: 2);
        $copyeditingWarning = max(1, (int) $this->getData('copyeditingWarning') ?: 10);
        $timezone = trim((string) $this->getData('timezone')) ?: 'Asia/Jakarta';
        $enableDailyDigest = (bool) $this->getData('enableDailyDigest');
        $enableWeeklyDigest = (bool) $this->getData('enableWeeklyDigest');

        $this->plugin->updateSetting($ctxId, 'round1Limit', $round1Limit, 'int');
        $this->plugin->updateSetting($ctxId, 'round2Limit', $round2Limit, 'int');
        $this->plugin->updateSetting($ctxId, 'round1Warning', $round1Warning, 'int');
        $this->plugin->updateSetting($ctxId, 'round2Warning', $round2Warning, 'int');
        $this->plugin->updateSetting($ctxId, 'copyeditingWarning', $copyeditingWarning, 'int');
        $this->plugin->updateSetting($ctxId, 'timezone', $timezone, 'string');
        $this->plugin->updateSetting($ctxId, 'enableDailyDigest', $enableDailyDigest, 'bool');
        $this->plugin->updateSetting($ctxId, 'enableWeeklyDigest', $enableWeeklyDigest, 'bool');

        if (Validation::isSiteAdmin() && $this->getData('directorUsernames') !== null) {
            $this->plugin->updateSetting(0, 'directorUsernames', trim((string) $this->getData('directorUsernames')), 'string');
        }

        // Record audit trail (Section 10)
        $dao = new EditorialReminderDAO();
        if ($user) {
            $dao->logAudit(
                $ctxId,
                (int) $user->getId(),
                'settings_updated',
                json_encode([
                    'round1Limit' => $round1Limit,
                    'round2Limit' => $round2Limit,
                    'round1Warning' => $round1Warning,
                    'round2Warning' => $round2Warning,
                    'copyeditingWarning' => $copyeditingWarning,
                    'timezone' => $timezone,
                ]),
            );
        }

        import('classes.notification.NotificationManager');
        $notificationMgr = new NotificationManager();
        if ($user) {
            $notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, ['contents' => __('common.changesSaved')]);
        }

        return parent::execute(...$functionArgs);
    }
}
