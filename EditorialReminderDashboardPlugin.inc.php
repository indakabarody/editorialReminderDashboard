<?php
/**
 * @file plugins/generic/editorialReminderDashboard/EditorialReminderDashboardPlugin.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderDashboardPlugin
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Main plugin class for the Editorial Reminder Dashboard plugin (OJS 3.3).
 */

import('lib.pkp.classes.plugins.GenericPlugin');

use Illuminate\Database\Capsule\Manager as Capsule;

class EditorialReminderDashboardPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return $success;
        }

        if ($success && $this->isPluginActive($mainContextId)) {
            // Register custom page handler for journal & site-level dashboards
            HookRegistry::register('LoadHandler', [$this, 'callbackLoadHandler']);

            // Inject Editorial Reminder item into OJS 3.3 backend sidebar navigation
            HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'callbackSetupBackendPage']);

            // Add link in Site Administration > Site Management
            HookRegistry::register('Templates::Admin::Index::SiteManagement', [$this, 'callbackAdminSiteManagement']);

            // Register scheduled task for daily/weekly email digests
            HookRegistry::register('AcronPlugin::parseCronTab', [$this, 'callbackParseCronTab']);
        }

        return $success;
    }

    /**
     * Determine whether the plugin is active in the current context or site-wide.
     * When accessed at the site level (contextId = 0), returns true if enabled site-wide OR in at least one journal.
     *
     * @param int|null $contextId
     * @return bool
     */
    public function isPluginActive($contextId = null)
    {
        if ($this->getEnabled($contextId)) {
            return true;
        }

        $request = Application::get()->getRequest();
        $context = $request ? $request->getContext() : null;
        if (!$context) {
            try {
                $enabledAnywhere = Capsule::table('plugin_settings')->where('plugin_name', '=', strtolower($this->getName()))->where('setting_name', '=', 'enabled')->where('setting_value', '=', '1')->exists();
                if ($enabledAnywhere) {
                    return true;
                }
            } catch (Exception $e) {
                // Ignore DB error during installation
            }
        }

        return false;
    }

    /**
     * @copydoc Plugin::isSitePlugin()
     */
    public function isSitePlugin()
    {
        $request = Application::get()->getRequest();
        return !$request || !$request->getContext();
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.editorialReminderDashboard.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.editorialReminderDashboard.description');
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration()
    {
        $this->import('EditorialReminderSchemaMigration');
        return new EditorialReminderSchemaMigration();
    }

    /**
     * Route requests for the `editorialReminder` page to EditorialReminderHandler.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackLoadHandler($hookName, $args)
    {
        $page = &$args[0];
        $op = &$args[1];

        if ($page === 'editorialReminder') {
            define('HANDLER_CLASS', 'EditorialReminderHandler');
            $this->import('pages.EditorialReminderHandler');
            EditorialReminderHandler::setPlugin($this);
            return true;
        }

        return false;
    }

    /**
     * Add Editorial Reminder link to the OJS 3.3 backend sidebar menu (Section 9).
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackSetupBackendPage($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        if (!$user) {
            return false;
        }

        $templateMgr = TemplateManager::getManager($request);
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        $this->import('classes.EditorialReminderService');
        $service = new EditorialReminderService($this);
        $dao = $service->getDAO();

        $isDirectorOrAdmin = $service->isUserDirectorOrSiteAdmin($user);
        $requestedPage = $router->getRequestedPage($request);
        $requestedOp = $router->getRequestedOp($request);

        $menu = (array) $templateMgr->getState('menu');

        if ($context) {
            $access = $dao->getUserContextRoleFlags((int) $user->getId(), (int) $context->getId());
            if (!$access['isSiteAdmin'] && !$access['isManager'] && !$access['isSubEditor'] && !$isDirectorOrAdmin) {
                return false;
            }

            $journalDashUrl = $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder');
            if ($isDirectorOrAdmin) {
                $siteDashUrl = $dispatcher->url($request, ROUTE_PAGE, 'index', 'editorialReminder', 'site');
                $navItem = [
                    'name' => __('plugins.generic.editorialReminderDashboard.menu.title'),
                    'submenu' => [
                        'editorialReminderJournal' => [
                            'name' => __('plugins.generic.editorialReminderDashboard.menu.journalDashboard'),
                            'url' => $journalDashUrl,
                            'isCurrent' => $requestedPage === 'editorialReminder' && $requestedOp !== 'site',
                        ],
                        'editorialReminderSite' => [
                            'name' => __('plugins.generic.editorialReminderDashboard.menu.siteDashboard'),
                            'url' => $siteDashUrl,
                            'isCurrent' => $requestedPage === 'editorialReminder' && $requestedOp === 'site',
                        ],
                    ],
                ];
            } else {
                $navItem = [
                    'name' => __('plugins.generic.editorialReminderDashboard.menu.title'),
                    'url' => $journalDashUrl,
                    'isCurrent' => $requestedPage === 'editorialReminder',
                ];
            }

            // Insert right after 'submissions' if present
            $newMenu = [];
            $inserted = false;
            foreach ($menu as $k => $v) {
                $newMenu[$k] = $v;
                if ($k === 'submissions') {
                    $newMenu['editorialReminder'] = $navItem;
                    $inserted = true;
                }
            }
            if (!$inserted) {
                $newMenu['editorialReminder'] = $navItem;
            }
            $templateMgr->setState(['menu' => $newMenu]);
        } elseif ($isDirectorOrAdmin) {
            $siteDashUrl = $dispatcher->url($request, ROUTE_PAGE, 'index', 'editorialReminder', 'site');
            $menu['editorialReminder'] = [
                'name' => __('plugins.generic.editorialReminderDashboard.menu.siteDashboard'),
                'url' => $siteDashUrl,
                'isCurrent' => $requestedPage === 'editorialReminder',
            ];
            $templateMgr->setState(['menu' => $menu]);
        }

        return false;
    }

    /**
     * Add link to Publishing House Dashboard inside Administration > Site Management.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackAdminSiteManagement($hookName, $args)
    {
        $output = &$args[2];
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $url = $dispatcher->url($request, ROUTE_PAGE, 'index', 'editorialReminder', 'site');
        $label = htmlspecialchars(__('plugins.generic.editorialReminderDashboard.menu.siteDashboardFull'), ENT_QUOTES, 'UTF-8');

        $output .= '<li><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a></li>';
        return false;
    }

    /**
     * Register scheduled tasks file with AcronPlugin.
     *
     * @param string $hookName
     * @param array $args
     * @return bool
     */
    public function callbackParseCronTab($hookName, $args)
    {
        $taskFilesPath = &$args[0];
        $taskFilesPath[] = $this->getPluginPath() . '/scheduledTasks.xml';
        return false;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        import('lib.pkp.classes.linkAction.request.RedirectAction');

        $dashboardUrl = $context ? $dispatcher->url($request, ROUTE_PAGE, $context->getPath(), 'editorialReminder') : $dispatcher->url($request, ROUTE_PAGE, 'index', 'editorialReminder', 'site');

        $openDashboardAction = new LinkAction('openDashboard', new RedirectAction($dashboardUrl), __('plugins.generic.editorialReminderDashboard.action.openDashboard'), null);

        $router = $request->getRouter();
        $settingsAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, [
                    'verb' => 'settings',
                    'plugin' => $this->getName(),
                    'category' => 'generic',
                ]),
                $this->getDisplayName(),
            ),
            __('manager.plugins.settings'),
            null,
        );

        array_unshift($actions, $openDashboardAction, $settingsAction);
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $this->import('EditorialReminderSettingsForm');
                $form = new EditorialReminderSettingsForm($this);

                if (!$request->getUserVar('save')) {
                    $form->initData();
                    return new JSONMessage(true, $form->fetch($request));
                }

                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
                return new JSONMessage(true, $form->fetch($request));
        }

        return parent::manage($args, $request);
    }
}
