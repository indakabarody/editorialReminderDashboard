<?php
/**
 * @file plugins/generic/editorialReminderDashboard/pages/EditorialReminderHandler.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderHandler
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Backend page handler for the Editorial Reminder Dashboard (per-journal and site-wide Publishing House),
 * exports (Excel, CSV, PDF), and CSRF-protected AJAX management endpoints.
 */

import('classes.handler.Handler');

require_once dirname(__DIR__) . '/classes/EditorialReminderService.inc.php';
require_once dirname(__DIR__) . '/classes/EditorialReminderExporter.inc.php';

class EditorialReminderHandler extends Handler
{
	/** @copydoc PKPHandler::_isBackendPage */
	public $_isBackendPage = true;

	/** @var EditorialReminderDashboardPlugin */
	protected static $_plugin;

	/**
	 * Inject the plugin instance.
	 *
	 * @param EditorialReminderDashboardPlugin $plugin
	 * @return void
	 */
	public static function setPlugin($plugin)
	{
		self::$_plugin = $plugin;
	}

	/**
	 * Get the plugin instance.
	 *
	 * @return EditorialReminderDashboardPlugin
	 */
	public static function getPlugin()
	{
		return self::$_plugin;
	}

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Authorize request based on OJS user roles and editorial permissions.
	 *
	 * @copydoc PKPHandler::authorize()
	 */
    public function authorize($request, &$args, $roleAssignments)
    {
        $user = $request->getUser();
        if (!$user) {
            Validation::redirectLogin();
            return false;
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Render the per-journal Editorial Reminder Dashboard (or redirect to site dashboard if at site context).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function index($args, $request)
    {
        $context = $request->getContext();
        if (!$context) {
            return $this->site($args, $request);
        }

        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();
        $user = $request->getUser();

        $access = $this->_resolveJournalAccess($user, (int) $context->getId(), $service, $dao);
        if (!$access['canView']) {
            $this->_renderAccessDenied($request);
            return;
        }

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        $locale = AppLocale::getLocale();

        $filters = $this->_readFiltersFromRequest($request);
        $dashboardData = $service->getJournalDashboardData((int) $context->getId(), $filters, $access['restrictToEditorId'], $access['canViewReviewerIdentity'], $locale);

        $optOutKey = 'optOutUser_' . (int) $user->getId();
        $userNotificationOptOut = (bool) $plugin->getSetting(0, $optOutKey);

        $templateMgr->addStyleSheet('editorialReminderStyles', $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/styles/editorialReminder.css', ['contexts' => 'backend']);
        $templateMgr->addJavaScript('editorialReminderScript', $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/js/editorialReminder.js', ['contexts' => 'backend']);

        $auditLogs = $access['canManageSettings'] ? $dao->getRecentAuditLogs((int) $context->getId(), 15, $locale) : [];

        $templateMgr->assign([
            'pageTitle' => __('plugins.generic.editorialReminderDashboard.displayName') . ' – ' . $context->getLocalizedName(),
            'journalName' => $context->getLocalizedName(),
            'journalAcronym' => $context->getLocalizedData('acronym') ?: $context->getLocalizedName(),
            'contextId' => (int) $context->getId(),
            'dashboardData' => $dashboardData,
            'summary' => $dashboardData['summary'],
            'items' => $dashboardData['items'],
            'settings' => $dashboardData['settings'],
            'holidays' => $dashboardData['holidays'],
            'unpublishedIssues' => $dashboardData['unpublishedIssues'],
            'allIssues' => $dashboardData['issues'],
            'editorsFilterMap' => $dashboardData['editorsFilterMap'],
            'activeFilters' => $filters,
            'access' => $access,
            'auditLogs' => $auditLogs,
            'userNotificationOptOut' => $userNotificationOptOut,
            'csrfToken' => $request->getSession()->getCSRFToken(),
            'baseDashboardUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder'),
            'siteDashboardUrl' => $dispatcher->url($request, ROUTE_PAGE, 'index', 'editorialReminder', 'site'),
            'exportUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder', 'export'),
            'saveTargetIssueUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder', 'saveTargetIssue'),
            'saveIssueTargetDateUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder', 'saveIssueTargetDate'),
            'saveHolidayUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder', 'saveHoliday'),
            'deleteHolidayUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder', 'deleteHoliday'),
            'saveSettingsUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder', 'saveSettings'),
            'workflowBaseUrl' => $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'index'),
        ]);

        $templateMgr->display($plugin->getTemplateResource('dashboard.tpl'));
    }

    /**
     * Render the Site-level Publishing House Dashboard.
     * Only accessible to Site Administrators and designated Directors.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function site($args, $request)
    {
        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();
        $user = $request->getUser();

        if (!$service->isUserDirectorOrSiteAdmin($user)) {
            $this->_renderAccessDenied($request);
            return;
        }

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $router = $request->getRouter();
        $locale = AppLocale::getLocale();

        $filters = [
            'stage' => trim((string) $request->getUserVar('stage')) ?: 'all',
            'sort' => trim((string) $request->getUserVar('sort')) ?: 'most_overdue',
        ];

        $siteData = $service->getSiteDashboardData($filters, $locale);
        $siteSettings = $service->getContextSettings(0);
        $globalHolidays = $dao->getHolidays(0);

        $dispatcher = $request->getDispatcher();

        // Build URLs for each journal dashboard
        foreach ($siteData['journalRows'] as &$jr) {
            $jr['dashboardUrl'] = $dispatcher->url($request, ROUTE_PAGE, $jr['path'], 'editorialReminder');
        }
        unset($jr);

        foreach ($siteData['urgentItems'] as &$uit) {
            $uit['workflowUrl'] = $dispatcher->url($request, ROUTE_PAGE, $uit['journalPath'], 'workflow', 'index', [$uit['id'], $uit['stageId']]);
            $uit['journalDashboardUrl'] = $dispatcher->url($request, ROUTE_PAGE, $uit['journalPath'], 'editorialReminder');
        }
        unset($uit);

        $templateMgr->addStyleSheet('editorialReminderStyles', $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/styles/editorialReminder.css', ['contexts' => 'backend']);
        $templateMgr->addJavaScript('editorialReminderScript', $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/js/editorialReminder.js', ['contexts' => 'backend']);

        $site = $request->getSite();
        $siteTitle = $site ? $site->getLocalizedTitle() : __('common.software');
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : 'index';

        $templateMgr->assign([
            'pageTitle' => __('plugins.generic.editorialReminderDashboard.site.heading') . ' – ' . $siteTitle,
            'siteTitle' => $siteTitle,
            'siteData' => $siteData,
            'summary' => $siteData['summary'],
            'journalRows' => $siteData['journalRows'],
            'urgentItems' => $siteData['urgentItems'],
            'activeFilters' => $filters,
            'settings' => $siteSettings,
            'holidays' => $globalHolidays,
            'isSiteAdmin' => Validation::isSiteAdmin(),
            'csrfToken' => $request->getSession()->getCSRFToken(),
            'siteDashboardUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'editorialReminder', 'site'),
            'exportSiteUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'editorialReminder', 'exportSite'),
            'saveHolidayUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'editorialReminder', 'saveHoliday'),
            'deleteHolidayUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'editorialReminder', 'deleteHoliday'),
            'saveSettingsUrl' => $dispatcher->url($request, ROUTE_PAGE, $contextPath, 'editorialReminder', 'saveSettings'),
        ]);

        $templateMgr->display($plugin->getTemplateResource('siteDashboard.tpl'));
    }

    /**
     * Export Journal Dashboard data to Excel (.xlsx), CSV (.csv), or PDF (.pdf).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function export($args, $request)
    {
        $context = $request->getContext();
        if (!$context) {
            return $this->exportSite($args, $request);
        }

        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();
        $user = $request->getUser();

        $access = $this->_resolveJournalAccess($user, (int) $context->getId(), $service, $dao);
        if (!$access['canView']) {
            $this->_renderAccessDenied($request);
            return;
        }

        $locale = AppLocale::getLocale();
        $filters = $this->_readFiltersFromRequest($request);
        $format = trim((string) $request->getUserVar('format')) ?: 'xlsx';

        $dashboardData = $service->getJournalDashboardData((int) $context->getId(), $filters, $access['restrictToEditorId'], $access['canViewReviewerIdentity'], $locale);

        $userNames = $dao->getUserFullNames([(int) $user->getId()], $locale);
        $exporterName = isset($userNames[(int) $user->getId()]) ? $userNames[(int) $user->getId()] : $user->getUsername();

        $metadata = [
            'reportTitle' => __('plugins.generic.editorialReminderDashboard.displayName'),
            'journalName' => $context->getLocalizedName(),
            'exportedAt' => date('Y-m-d H:i:s'),
            'activeFiltersText' => $this->_describeActiveFilters($filters, $dashboardData),
            'exportedBy' => $exporterName,
        ];

        $exporter = new EditorialReminderExporter();
        $exporter->exportJournalReport($format, $dashboardData, $metadata);
    }

    /**
     * Export Site-level Publishing House data to Excel (.xlsx), CSV (.csv), or PDF (.pdf).
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function exportSite($args, $request)
    {
        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();
        $user = $request->getUser();

        if (!$service->isUserDirectorOrSiteAdmin($user)) {
            $this->_renderAccessDenied($request);
            return;
        }

        $locale = AppLocale::getLocale();
        $filters = [
            'stage' => trim((string) $request->getUserVar('stage')) ?: 'all',
            'sort' => trim((string) $request->getUserVar('sort')) ?: 'most_overdue',
        ];
        $format = trim((string) $request->getUserVar('format')) ?: 'xlsx';

        $siteData = $service->getSiteDashboardData($filters, $locale);
        $userNames = $dao->getUserFullNames([(int) $user->getId()], $locale);
        $exporterName = isset($userNames[(int) $user->getId()]) ? $userNames[(int) $user->getId()] : $user->getUsername();
        $site = $request->getSite();
        $siteTitle = $site ? $site->getLocalizedTitle() : __('plugins.generic.editorialReminderDashboard.site.publishingHouse');

        $metadata = [
            'reportTitle' => __('plugins.generic.editorialReminderDashboard.site.heading'),
            'journalName' => $siteTitle,
            'exportedAt' => date('Y-m-d H:i:s'),
            'activeFiltersText' => __('plugins.generic.editorialReminderDashboard.filter.stage') . ': ' . $filters['stage'] . ' | ' . __('plugins.generic.editorialReminderDashboard.filter.sort') . ': ' . $filters['sort'],
            'exportedBy' => $exporterName,
        ];

        $exporter = new EditorialReminderExporter();
        $exporter->exportSiteReport($format, $siteData, $metadata);
    }

    /**
     * AJAX endpoint: Save Target Issue for a Copyediting submission.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function saveTargetIssue($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context || !$user) {
            $this->_sendJson(['ok' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();

        $access = $this->_resolveJournalAccess($user, (int) $context->getId(), $service, $dao);
        if (!$access['canEditTargetIssue']) {
            $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.accessDenied')], 403);
            return;
        }

        $submissionId = (int) $request->getUserVar('submissionId');
        $issueIdRaw = $request->getUserVar('issueId');
        $issueId = !empty($issueIdRaw) && $issueIdRaw !== 'none' ? (int) $issueIdRaw : null;

        $issuesMap = $dao->getIssuesWithTargetDates((int) $context->getId(), AppLocale::getLocale());
        $targetDate = $issueId && isset($issuesMap[$issueId]) ? $issuesMap[$issueId]['targetDate'] : null;

        $dao->setSubmissionTargetIssue($submissionId, (int) $context->getId(), $issueId, $targetDate, (int) $user->getId());

        $this->_sendJson([
            'ok' => true,
            'message' => __('common.changesSaved'),
            'submissionId' => $submissionId,
            'issueId' => $issueId,
            'targetDate' => $targetDate,
        ]);
    }

    /**
     * AJAX endpoint: Save custom Target Publication Date H for an Issue.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function saveIssueTargetDate($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $context = $request->getContext();
        $user = $request->getUser();
        if (!$context || !$user) {
            $this->_sendJson(['ok' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();

        $access = $this->_resolveJournalAccess($user, (int) $context->getId(), $service, $dao);
        if (!$access['canManageSettings']) {
            $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.accessDenied')], 403);
            return;
        }

        $issueId = (int) $request->getUserVar('issueId');
        $targetDate = trim((string) $request->getUserVar('targetDate'));
        if (!$issueId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.invalidDate')], 400);
            return;
        }

        $dao->setIssueTargetDate($issueId, (int) $context->getId(), $targetDate, (int) $user->getId());

        $this->_sendJson([
            'ok' => true,
            'message' => __('common.changesSaved'),
            'issueId' => $issueId,
            'targetDate' => $targetDate,
        ]);
    }

    /**
     * AJAX endpoint: Add a holiday entry.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function saveHoliday($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $user = $request->getUser();
        if (!$user) {
            $this->_sendJson(['ok' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();
        $context = $request->getContext();
        $isGlobal = (int) $request->getUserVar('isGlobal') === 1 || !$context;

        if ($isGlobal) {
            if (!Validation::isSiteAdmin()) {
                $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.accessDenied')], 403);
                return;
            }
            $targetContextId = 0;
        } else {
            $access = $this->_resolveJournalAccess($user, (int) $context->getId(), $service, $dao);
            if (!$access['canManageSettings']) {
                $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.accessDenied')], 403);
                return;
            }
            $targetContextId = (int) $context->getId();
        }

        $holidayDate = trim((string) $request->getUserVar('holidayDate'));
        $description = trim((string) $request->getUserVar('description'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate) || $description === '') {
            $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.invalidHoliday')], 400);
            return;
        }

        $holidayId = $dao->addHoliday($targetContextId, $holidayDate, $description, (int) $user->getId());

        $this->_sendJson([
            'ok' => true,
            'message' => __('common.changesSaved'),
            'holidayId' => $holidayId,
            'holidayDate' => $holidayDate,
            'description' => $description,
        ]);
    }

    /**
     * AJAX endpoint: Delete a holiday entry.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function deleteHoliday($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $user = $request->getUser();
        if (!$user) {
            $this->_sendJson(['ok' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();
        $context = $request->getContext();

        $contextId = $context ? (int) $context->getId() : 0;
        if (Validation::isSiteAdmin()) {
            $contextId = 0; // Site admin can delete any holiday
        } else {
            $access = $this->_resolveJournalAccess($user, $contextId, $service, $dao);
            if (!$access['canManageSettings']) {
                $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.accessDenied')], 403);
                return;
            }
        }

        $holidayId = (int) $request->getUserVar('holidayId');
        $dao->deleteHoliday($holidayId, $contextId, (int) $user->getId());

        $this->_sendJson([
            'ok' => true,
            'message' => __('common.changesSaved'),
            'holidayId' => $holidayId,
        ]);
    }

    /**
     * AJAX endpoint: Save journal/global settings or user notification preferences from the dashboard modal.
     *
     * @param array $args
     * @param PKPRequest $request
     * @return void
     */
    public function saveSettings($args, $request)
    {
        if (!$request->checkCSRF()) {
            $this->_sendJson(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $user = $request->getUser();
        if (!$user) {
            $this->_sendJson(['ok' => false, 'message' => 'Unauthorized'], 403);
            return;
        }

        $plugin = self::getPlugin();
        $service = new EditorialReminderService($plugin);
        $dao = $service->getDAO();
        $context = $request->getContext();

        // User notification preference toggle - available to any editorial user
        if ($request->getUserVar('updateNotificationPrefOnly')) {
            $optOut = (int) $request->getUserVar('notificationOptOut') === 1;
            $plugin->updateSetting(0, 'optOutUser_' . (int) $user->getId(), $optOut, 'bool');
            $this->_sendJson(['ok' => true, 'message' => __('common.changesSaved')]);
            return;
        }

        $targetContextId = $context ? (int) $context->getId() : 0;
        if ((int) $request->getUserVar('isGlobalSettings') === 1) {
            $targetContextId = 0;
        }

        if ($targetContextId === 0) {
            if (!Validation::isSiteAdmin()) {
                $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.accessDenied')], 403);
                return;
            }
        } else {
            $access = $this->_resolveJournalAccess($user, $targetContextId, $service, $dao);
            if (!$access['canManageSettings']) {
                $this->_sendJson(['ok' => false, 'message' => __('plugins.generic.editorialReminderDashboard.error.accessDenied')], 403);
                return;
            }
        }

        $round1Limit = max(14, min(21, (int) $request->getUserVar('round1Limit') ?: 21));
        $round2Limit = max(1, (int) $request->getUserVar('round2Limit') ?: 7);
        $round1Warning = max(1, (int) $request->getUserVar('round1Warning') ?: 3);
        $round2Warning = max(1, (int) $request->getUserVar('round2Warning') ?: 2);
        $copyeditingWarning = max(1, (int) $request->getUserVar('copyeditingWarning') ?: 10);
        $timezone = trim((string) $request->getUserVar('timezone')) ?: 'Asia/Jakarta';
        $enableDailyDigest = (int) $request->getUserVar('enableDailyDigest') === 1;
        $enableWeeklyDigest = (int) $request->getUserVar('enableWeeklyDigest') === 1;

        $plugin->updateSetting($targetContextId, 'round1Limit', $round1Limit, 'int');
        $plugin->updateSetting($targetContextId, 'round2Limit', $round2Limit, 'int');
        $plugin->updateSetting($targetContextId, 'round1Warning', $round1Warning, 'int');
        $plugin->updateSetting($targetContextId, 'round2Warning', $round2Warning, 'int');
        $plugin->updateSetting($targetContextId, 'copyeditingWarning', $copyeditingWarning, 'int');
        $plugin->updateSetting($targetContextId, 'timezone', $timezone, 'string');
        $plugin->updateSetting($targetContextId, 'enableDailyDigest', $enableDailyDigest, 'bool');
        $plugin->updateSetting($targetContextId, 'enableWeeklyDigest', $enableWeeklyDigest, 'bool');

        if (Validation::isSiteAdmin() && $request->getUserVar('directorUsernames') !== null) {
            $plugin->updateSetting(0, 'directorUsernames', trim((string) $request->getUserVar('directorUsernames')), 'string');
        }

        $optOut = (int) $request->getUserVar('notificationOptOut') === 1;
        $plugin->updateSetting(0, 'optOutUser_' . (int) $user->getId(), $optOut, 'bool');

        $dao->logAudit(
            $targetContextId,
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

        $this->_sendJson([
            'ok' => true,
            'message' => __('common.changesSaved'),
        ]);
    }

	/**
	 * Determine user's access level for a given journal according to editorial roles.
	 *
	 * @param User $user
	 * @param int $contextId
	 * @param EditorialReminderService $service
	 * @param EditorialReminderDAO $dao
	 * @return array
	 */
	protected function _resolveJournalAccess($user, $contextId, $service, $dao)
	{
		$flags = $dao->getUserContextRoleFlags((int) $user->getId(), (int) $contextId);
		$isDirector = $service->isUserDirectorOrSiteAdmin($user) && !$flags['isSiteAdmin'];

		$canView = $flags['isSiteAdmin'] || $isDirector || $flags['isManager'] || $flags['isSubEditor'];
		$isSectionEditorOnly = $flags['isSubEditor'] && !$flags['isManager'] && !$flags['isSiteAdmin'] && !$isDirector;

		return [
			'canView' => $canView,
			'isSiteAdmin' => $flags['isSiteAdmin'],
			'isDirector' => $isDirector,
			'isManager' => $flags['isManager'],
			'isSubEditor' => $flags['isSubEditor'],
			'isSectionEditorOnly' => $isSectionEditorOnly,
			'restrictToEditorId' => $isSectionEditorOnly ? (int) $user->getId() : null,
			'canManageSettings' => $flags['isSiteAdmin'] || $flags['isManager'],
			'canEditTargetIssue' => $flags['isSiteAdmin'] || $flags['isManager'] || $flags['isSubEditor'],
			'canAccessSiteDashboard' => $flags['isSiteAdmin'] || $isDirector,
			'canViewReviewerIdentity' => $canView,
		];
	}

	/**
	 * Read filter query parameters from the request.
	 *
	 * @param PKPRequest $request
	 * @return array
	 */
	protected function _readFiltersFromRequest($request)
	{
		return [
			'stage' => trim((string) $request->getUserVar('stage')) ?: 'all',
			'status' => trim((string) $request->getUserVar('status')) ?: 'all',
			'editorId' => trim((string) $request->getUserVar('editorId')) ?: 'all',
			'issueId' => trim((string) $request->getUserVar('issueId')) ?: 'all',
			'search' => trim((string) $request->getUserVar('search')),
			'sort' => trim((string) $request->getUserVar('sort')) ?: 'urgency',
		];
	}

	/**
	 * Build a concise human-readable description of active filters for exports.
	 *
	 * @param array $filters
	 * @param array $dashboardData
	 * @return string
	 */
	protected function _describeActiveFilters($filters, $dashboardData)
	{
		$parts = [];
		$parts[] = __('plugins.generic.editorialReminderDashboard.filter.stage') . ': ' . ($filters['stage'] ?: 'all');
		$parts[] = __('plugins.generic.editorialReminderDashboard.filter.status') . ': ' . ($filters['status'] ?: 'all');

		if ($filters['editorId'] !== 'all' && $filters['editorId'] !== '') {
			$edId = (int) $filters['editorId'];
			$edName = isset($dashboardData['editorsFilterMap'][$edId]) ? $dashboardData['editorsFilterMap'][$edId] : '#' . $edId;
			$parts[] = __('plugins.generic.editorialReminderDashboard.filter.editor') . ': ' . $edName;
		}
		if ($filters['issueId'] !== 'all' && $filters['issueId'] !== '') {
			$issId = $filters['issueId'];
			if ($issId === 'none') {
				$parts[] = __('plugins.generic.editorialReminderDashboard.filter.issue') . ': ' . __('plugins.generic.editorialReminderDashboard.status.noTarget');
			} elseif (isset($dashboardData['issues'][(int) $issId])) {
				$parts[] = __('plugins.generic.editorialReminderDashboard.filter.issue') . ': ' . $dashboardData['issues'][(int) $issId]['shortLabel'];
			}
		}
		if (!empty($filters['search'])) {
			$parts[] = __('common.search') . ': "' . $filters['search'] . '"';
		}

		return implode(' | ', $parts);
	}

	/**
	 * Render 403 Access Denied response.
     *
     * @param PKPRequest $request
     * @return void
     */
    protected function _renderAccessDenied($request)
    {
        if (!headers_sent()) {
            header('HTTP/1.0 403 Forbidden');
        }
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('common.error'),
            'errorMsg' => 'plugins.generic.editorialReminderDashboard.error.accessDenied',
            'backLink' => $request->getBaseUrl(),
            'backLinkLabel' => 'common.back',
        ]);
        $templateMgr->display('frontend/pages/error.tpl');
    }

    /**
     * Output JSON response and terminate.
     *
     * @param array $payload
     * @param int $statusCode
     * @return void
     */
    protected function _sendJson($payload, $statusCode = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($payload);
        exit();
    }
}
