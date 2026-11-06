{**
 * templates/siteDashboard.tpl
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Site-level (Publishing House) Editorial Reminder Dashboard template.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="er-dashboard" id="erSiteDashboard"
	data-base-url="{$siteDashboardUrl|escape}"
	data-export-url="{$exportSiteUrl|escape}"
	data-save-holiday-url="{$saveHolidayUrl|escape}"
	data-delete-holiday-url="{$deleteHolidayUrl|escape}"
	data-save-settings-url="{$saveSettingsUrl|escape}"
	data-csrf-token="{$csrfToken|escape}">

	{* Top Dark Teal Banner matching Mockup Page 2 *}
	<div class="er-banner">
		<div class="er-banner__left">
			<span class="er-banner__journal">{$siteTitle|escape} – {translate key="plugins.generic.editorialReminderDashboard.site.publishingHouse"}</span>
			<span class="er-banner__subtitle">{translate key="plugins.generic.editorialReminderDashboard.site.bannerSubtitle"}</span>
		</div>
		<div class="er-banner__actions">
			{if $isSiteAdmin}
				<button type="button" class="er-btn er-btn--banner" id="erOpenSettingsBtn">
					{translate key="plugins.generic.editorialReminderDashboard.settings.globalTitle"}
				</button>
			{/if}
		</div>
	</div>

	<div class="er-container">
		<div class="er-header-row">
			<h1 class="er-title">{translate key="plugins.generic.editorialReminderDashboard.site.heading"}</h1>
		</div>

		{* 5 Summary Cards matching Mockup Page 2 *}
		<div class="er-cards er-cards--five">
			<div class="er-card">
				<div class="er-card__label">{translate key="plugins.generic.editorialReminderDashboard.card.monitoredJournals"}</div>
				<div class="er-card__value">{$summary.journalCount|escape}</div>
			</div>
			<div class="er-card">
				<div class="er-card__label">{translate key="plugins.generic.editorialReminderDashboard.card.monitoredArticles"}</div>
				<div class="er-card__value">{$summary.total|escape}</div>
			</div>
			<div class="er-card">
				<div class="er-card__label er-card__label--normal">
					<span aria-hidden="true">●</span> {translate key="plugins.generic.editorialReminderDashboard.status.normal"}
				</div>
				<div class="er-card__value">{$summary.normal|escape}</div>
			</div>
			<div class="er-card">
				<div class="er-card__label er-card__label--almost-due">
					<span aria-hidden="true">▲</span> {translate key="plugins.generic.editorialReminderDashboard.status.almostDue"}
				</div>
				<div class="er-card__value">{$summary.almostDue|escape}</div>
			</div>
			<div class="er-card">
				<div class="er-card__label er-card__label--overdue">
					<span aria-hidden="true">■</span> {translate key="plugins.generic.editorialReminderDashboard.status.overdue"}
				</div>
				<div class="er-card__value">{$summary.overdue|escape}</div>
			</div>
		</div>

		{* Toolbar: Stage filter, Sort, and Exports *}
		<form id="erFilterForm" class="er-toolbar" method="GET" action="{$siteDashboardUrl|escape}">
			<div class="er-filters">
				<select name="stage" class="er-select er-auto-submit" aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.stage"}">
					<option value="all" {if $activeFilters.stage == 'all'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.filter.stageAll"}</option>
					<option value="review" {if $activeFilters.stage == 'review'}selected{/if}>Review</option>
					<option value="copyediting" {if $activeFilters.stage == 'copyediting'}selected{/if}>Copyediting</option>
				</select>

				<select name="sort" class="er-select er-auto-submit" aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.sort"}">
					<option value="most_overdue" {if $activeFilters.sort == 'most_overdue'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.siteSort.mostOverdue"}</option>
					<option value="most_almost_due" {if $activeFilters.sort == 'most_almost_due'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.siteSort.mostAlmostDue"}</option>
					<option value="most_articles" {if $activeFilters.sort == 'most_articles'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.siteSort.mostArticles"}</option>
					<option value="name_asc" {if $activeFilters.sort == 'name_asc'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.siteSort.nameAsc"}</option>
				</select>
			</div>

			<div class="er-exports">
				<button type="button" class="er-btn er-btn--primary er-export-btn" data-format="xlsx">
					{translate key="plugins.generic.editorialReminderDashboard.export.siteRecapExcel"}
				</button>
				<button type="button" class="er-btn er-btn--outline er-export-btn" data-format="csv">
					{translate key="plugins.generic.editorialReminderDashboard.export.siteAllCsv"}
				</button>
				<button type="button" class="er-btn er-btn--outline er-export-btn" data-format="pdf">
					{translate key="plugins.generic.editorialReminderDashboard.export.pdf"}
				</button>
			</div>
		</form>

		{* Per-Journal Recap Table *}
		<div class="er-table-wrapper">
			<table class="er-table">
				<thead>
					<tr>
						<th>{translate key="plugins.generic.editorialReminderDashboard.siteTable.journal"}</th>
						<th>{translate key="plugins.generic.editorialReminderDashboard.siteTable.articles"}</th>
						<th class="er-col-distribution">{translate key="plugins.generic.editorialReminderDashboard.siteTable.distribution"}</th>
						<th>{translate key="plugins.generic.editorialReminderDashboard.status.normal"}</th>
						<th>{translate key="plugins.generic.editorialReminderDashboard.status.almostDue"}</th>
						<th>{translate key="plugins.generic.editorialReminderDashboard.status.overdue"}</th>
						<th class="er-col-action"></th>
					</tr>
				</thead>
				<tbody>
					{if empty($journalRows)}
						<tr>
							<td colspan="7" class="er-empty-row">{translate key="common.noItemsFound"}</td>
						</tr>
					{else}
						{foreach from=$journalRows item="jr"}
							<tr>
								<td class="er-journal-name-cell">
									<strong>{$jr.name|escape}</strong>
								</td>
								<td>{$jr.total|escape}</td>
								<td class="er-col-distribution">
									<div class="er-progress-bar" role="img"
										aria-label="{translate key="plugins.generic.editorialReminderDashboard.status.normal"}: {$jr.normal|escape}, {translate key="plugins.generic.editorialReminderDashboard.status.almostDue"}: {$jr.almostDue|escape}, {translate key="plugins.generic.editorialReminderDashboard.status.overdue"}: {$jr.overdue|escape}">
										{if $jr.total > 0}
											<div class="er-progress-segment er-progress-segment--normal" style="width: {$jr.normalPct|escape}%;"></div>
											<div class="er-progress-segment er-progress-segment--almost-due" style="width: {$jr.almostDuePct|escape}%;"></div>
											<div class="er-progress-segment er-progress-segment--overdue" style="width: {$jr.overduePct|escape}%;"></div>
											<div class="er-progress-segment er-progress-segment--no-target" style="width: {$jr.noTargetPct|escape}%;"></div>
										{/if}
									</div>
								</td>
								<td>{$jr.normal|escape}</td>
								<td>{$jr.almostDue|escape}</td>
								<td>{$jr.overdue|escape}</td>
								<td class="er-col-action">
									<a href="{$jr.dashboardUrl|escape}" class="er-btn er-btn--outline er-btn--sm">
										{translate key="plugins.generic.editorialReminderDashboard.action.openJournalDashboard"}
									</a>
								</td>
							</tr>
						{/foreach}
					{/if}
				</tbody>
			</table>
		</div>

		{* Most Urgent Articles Across All Journals *}
		<div class="er-urgent-section">
			<h2 class="er-section-title">{translate key="plugins.generic.editorialReminderDashboard.site.mostUrgentHeading"}</h2>
			<div class="er-table-wrapper">
				<table class="er-table">
					<thead>
						<tr>
							<th>{translate key="plugins.generic.editorialReminderDashboard.siteTable.journal"}</th>
							<th class="er-col-code">{translate key="plugins.generic.editorialReminderDashboard.table.code"}</th>
							<th>{translate key="plugins.generic.editorialReminderDashboard.table.stage"}</th>
							<th>{translate key="plugins.generic.editorialReminderDashboard.table.editor"}</th>
							<th>{translate key="plugins.generic.editorialReminderDashboard.table.dueDate"}</th>
							<th>{translate key="plugins.generic.editorialReminderDashboard.table.status"}</th>
						</tr>
					</thead>
					<tbody>
						{if empty($urgentItems)}
							<tr>
								<td colspan="6" class="er-empty-row">{translate key="plugins.generic.editorialReminderDashboard.table.empty"}</td>
							</tr>
						{else}
							{foreach from=$urgentItems item="uit"}
								<tr>
									<td>
										<a href="{$uit.journalDashboardUrl|escape}" class="er-article-link">
											{$uit.journalName|escape}
										</a>
									</td>
									<td class="er-col-code">
										<a href="{$uit.workflowUrl|escape}" class="er-code-link" title="{$uit.title|escape}">
											{$uit.id|escape}
										</a>
									</td>
									<td>{$uit.stageLabel|escape}</td>
									<td>{$uit.editorNames|escape}</td>
									<td>{$uit.dueDateFormatted|escape}</td>
									<td>
										<span class="er-badge er-badge--{$uit.status|escape}">
											<span aria-hidden="true">{$uit.statusSymbol|escape}</span>
											{$uit.timeRemainingText|escape}
										</span>
									</td>
								</tr>
							{/foreach}
						{/if}
					</tbody>
				</table>
			</div>
		</div>

		<div class="er-footer-note">
			{translate key="plugins.generic.editorialReminderDashboard.site.footerSummary" journalCount=$summary.journalCount}
		</div>
	</div>

	{* Global Settings Modal for Site Administrators *}
	{if $isSiteAdmin}
		<div id="erSettingsModal" class="er-modal" role="dialog" aria-modal="true" aria-labelledby="erSettingsModalTitle" hidden>
			<div class="er-modal__backdrop" id="erCloseSettingsBackdrop"></div>
			<div class="er-modal__content">
				<div class="er-modal__header">
					<h2 id="erSettingsModalTitle" class="er-modal__title">
						{translate key="plugins.generic.editorialReminderDashboard.settings.globalTitle"}
					</h2>
					<button type="button" class="er-modal__close" id="erCloseSettingsBtn" aria-label="{translate key="common.close"}">×</button>
				</div>

				<div class="er-modal__tabs" role="tablist">
					<button type="button" class="er-modal__tab er-modal__tab--active" data-modal-tab="rules">
						{translate key="plugins.generic.editorialReminderDashboard.settings.tabRules"}
					</button>
					<button type="button" class="er-modal__tab" data-modal-tab="holidays">
						{translate key="plugins.generic.editorialReminderDashboard.settings.tabHolidays"}
					</button>
				</div>

				<div class="er-modal__body">
					<div class="er-modal__pane" data-modal-pane="rules">
						<form id="erModalSettingsForm">
							<input type="hidden" name="isGlobalSettings" value="1" />
							<div class="er-form-grid">
								<div class="er-form-group">
									<label for="erSiteRound1Limit">{translate key="plugins.generic.editorialReminderDashboard.settings.round1Limit"}</label>
									<input type="number" id="erSiteRound1Limit" name="round1Limit" min="14" max="21" class="er-input" value="{$settings.round1Limit|escape}" />
								</div>
								<div class="er-form-group">
									<label for="erSiteRound2Limit">{translate key="plugins.generic.editorialReminderDashboard.settings.round2Limit"}</label>
									<input type="number" id="erSiteRound2Limit" name="round2Limit" min="1" max="60" class="er-input" value="{$settings.round2Limit|escape}" />
								</div>
								<div class="er-form-group">
									<label for="erSiteRound1Warn">{translate key="plugins.generic.editorialReminderDashboard.settings.round1Warning"}</label>
									<input type="number" id="erSiteRound1Warn" name="round1Warning" min="1" max="14" class="er-input" value="{$settings.round1Warning|escape}" />
								</div>
								<div class="er-form-group">
									<label for="erSiteRound2Warn">{translate key="plugins.generic.editorialReminderDashboard.settings.round2Warning"}</label>
									<input type="number" id="erSiteRound2Warn" name="round2Warning" min="1" max="14" class="er-input" value="{$settings.round2Warning|escape}" />
								</div>
								<div class="er-form-group">
									<label for="erSiteCeWarn">{translate key="plugins.generic.editorialReminderDashboard.settings.copyeditingWarning"}</label>
									<input type="number" id="erSiteCeWarn" name="copyeditingWarning" min="1" max="60" class="er-input" value="{$settings.copyeditingWarning|escape}" />
								</div>
								<div class="er-form-group">
									<label for="erSiteTimezone">{translate key="plugins.generic.editorialReminderDashboard.settings.timezone"}</label>
									<input type="text" id="erSiteTimezone" name="timezone" class="er-input" value="{$settings.timezone|escape}" />
								</div>
							</div>

							<div class="er-form-group er-form-group--full">
								<label for="erDirectorUsernames">{translate key="plugins.generic.editorialReminderDashboard.settings.directorUsernames"}</label>
								<input type="text" id="erDirectorUsernames" name="directorUsernames" class="er-input" value="{$settings.directorUsernames|escape}" />
								<small>{translate key="plugins.generic.editorialReminderDashboard.settings.directorUsernamesDesc"}</small>
							</div>

							<div class="er-modal__footer">
								<button type="submit" class="er-btn er-btn--primary">
									{translate key="common.save"}
								</button>
							</div>
						</form>
					</div>

					<div class="er-modal__pane" data-modal-pane="holidays" hidden>
						<p class="er-modal__help">{translate key="plugins.generic.editorialReminderDashboard.settings.holidayHelp"}</p>
						<form id="erAddHolidayForm" class="er-inline-form">
							<input type="hidden" name="isGlobal" value="1" />
							<input type="date" name="holidayDate" class="er-input" required />
							<input type="text" name="description" class="er-input"
								placeholder="{translate key="plugins.generic.editorialReminderDashboard.settings.holidayDescriptionPlaceholder"}" required />
							<button type="submit" class="er-btn er-btn--primary">
								{translate key="plugins.generic.editorialReminderDashboard.settings.addHoliday"}
							</button>
						</form>

						<table class="er-subtable">
							<thead>
								<tr>
									<th>{translate key="common.date"}</th>
									<th>{translate key="common.description"}</th>
									<th>{translate key="common.action"}</th>
								</tr>
							</thead>
							<tbody id="erHolidayTableBody">
								{if empty($holidays)}
									<tr>
										<td colspan="3">{translate key="plugins.generic.editorialReminderDashboard.settings.noHolidays"}</td>
									</tr>
								{else}
									{foreach from=$holidays item="hol"}
										<tr data-holiday-id="{$hol.id|escape}">
											<td>{$hol.date|escape}</td>
											<td>{$hol.description|escape}</td>
											<td>
												<button type="button" class="er-btn er-btn--danger-outline er-delete-holiday-btn" data-holiday-id="{$hol.id|escape}">
													{translate key="common.delete"}
												</button>
											</td>
										</tr>
									{/foreach}
								{/if}
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	{/if}
</div>
{/block}

