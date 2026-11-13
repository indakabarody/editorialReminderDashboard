{**
 * templates/dashboard.tpl
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Per-Journal Editorial Reminder Dashboard template.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="er-dashboard" id="erJournalDashboard"
	data-base-url="{$baseDashboardUrl|escape}"
	data-export-url="{$exportUrl|escape}"
	data-save-target-url="{$saveTargetIssueUrl|escape}"
	data-save-issue-date-url="{$saveIssueTargetDateUrl|escape}"
	data-save-holiday-url="{$saveHolidayUrl|escape}"
	data-delete-holiday-url="{$deleteHolidayUrl|escape}"
	data-save-settings-url="{$saveSettingsUrl|escape}"
	data-min-working-day-msg="{translate|escape key="plugins.generic.editorialReminderDashboard.error.minOneWorkingDay"}"
	data-csrf-token="{$csrfToken|escape}">

	{* Top Dark Teal Banner matching Mockup Page 1 *}
	<div class="er-banner">
		<div class="er-banner__left">
			<span class="er-banner__journal">{$journalName|escape}</span>
			<span class="er-banner__subtitle">{translate key="plugins.generic.editorialReminderDashboard.bannerSubtitle"}</span>
		</div>
		<div class="er-banner__actions">
			{if $access.canAccessSiteDashboard}
				<a href="{$siteDashboardUrl|escape}" class="er-btn er-btn--banner">
					{translate key="plugins.generic.editorialReminderDashboard.action.viewSiteDashboard"}
				</a>
			{/if}
			<button type="button" class="er-btn er-btn--banner" id="erOpenSettingsBtn">
				{translate key="plugins.generic.editorialReminderDashboard.action.manageSettings"}
			</button>
		</div>
	</div>

	<div class="er-container">
		<div class="er-header-row">
			<h1 class="er-title">
				{translate key="plugins.generic.editorialReminderDashboard.journalHeading" journal=$journalAcronym|escape}
			</h1>
		</div>

		{* Summary Cards *}
		<div class="er-cards {if $summary.noTarget > 0}er-cards--five{else}er-cards--four{/if}">
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
			{if $summary.noTarget > 0}
				<div class="er-card er-card--no-target">
					<div class="er-card__label er-card__label--no-target">
						<span aria-hidden="true">◆</span> {translate key="plugins.generic.editorialReminderDashboard.status.noTarget"}
					</div>
					<div class="er-card__value">{$summary.noTarget|escape}</div>
				</div>
			{/if}
		</div>

		{* Filter & Export Toolbar *}
		<form id="erFilterForm" class="er-toolbar" method="GET" action="{$baseDashboardUrl|escape}">
			<input type="hidden" name="stage" id="erStageInput" value="{$activeFilters.stage|escape}" />

			<div class="er-tabs" role="tablist" aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.stage"}">
				<button type="button" role="tab"
					aria-selected="{if $activeFilters.stage == 'all'}true{else}false{/if}"
					class="er-tab {if $activeFilters.stage == 'all'}er-tab--active{/if}"
					data-stage="all">
					{translate key="plugins.generic.editorialReminderDashboard.tab.all" count=$summary.total}
				</button>
				<button type="button" role="tab"
					aria-selected="{if $activeFilters.stage == 'review'}true{else}false{/if}"
					class="er-tab {if $activeFilters.stage == 'review'}er-tab--active{/if}"
					data-stage="review">
					{translate key="plugins.generic.editorialReminderDashboard.tab.review" count=$summary.reviewCount}
				</button>
				<button type="button" role="tab"
					aria-selected="{if $activeFilters.stage == 'copyediting'}true{else}false{/if}"
					class="er-tab {if $activeFilters.stage == 'copyediting'}er-tab--active{/if}"
					data-stage="copyediting">
					{translate key="plugins.generic.editorialReminderDashboard.tab.copyediting" count=$summary.copyeditingCount}
				</button>
			</div>

			<div class="er-filters">
				<div class="er-search-wrap">
					<input type="search" name="search" id="erSearchInput"
						class="er-input er-input--search"
						value="{$activeFilters.search|escape}"
						placeholder="{translate key="plugins.generic.editorialReminderDashboard.filter.searchPlaceholder"}"
						aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.searchPlaceholder"}" />
				</div>

				<select name="status" class="er-select er-auto-submit" aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.status"}">
					<option value="all" {if $activeFilters.status == 'all'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.filter.statusAll"}</option>
					<option value="overdue" {if $activeFilters.status == 'overdue'}selected{/if}>■ {translate key="plugins.generic.editorialReminderDashboard.status.overdue"}</option>
					<option value="almost_due" {if $activeFilters.status == 'almost_due'}selected{/if}>▲ {translate key="plugins.generic.editorialReminderDashboard.status.almostDue"}</option>
					<option value="normal" {if $activeFilters.status == 'normal'}selected{/if}>● {translate key="plugins.generic.editorialReminderDashboard.status.normal"}</option>
					<option value="no_target" {if $activeFilters.status == 'no_target'}selected{/if}>◆ {translate key="plugins.generic.editorialReminderDashboard.status.noTarget"}</option>
				</select>

				<select name="editorId" class="er-select er-auto-submit" aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.editor"}">
					<option value="all" {if $activeFilters.editorId == 'all'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.filter.editorAll"}</option>
					<option value="0" {if $activeFilters.editorId === '0'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.unassignedEditor"}</option>
					{foreach from=$editorsFilterMap key="edId" item="edName"}
						<option value="{$edId|escape}" {if $activeFilters.editorId == $edId}selected{/if}>{$edName|escape}</option>
					{/foreach}
				</select>

				<select name="issueId" class="er-select er-auto-submit" aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.issue"}">
					<option value="all" {if $activeFilters.issueId == 'all'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.filter.issueAll"}</option>
					<option value="none" {if $activeFilters.issueId == 'none'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.status.noTarget"}</option>
					{if !empty($futureIssues)}
						<optgroup label="{translate key="editor.issues.futureIssues"}">
							{foreach from=$futureIssues key="issId" item="iss"}
								<option value="{$issId|escape}" {if $activeFilters.issueId == $issId}selected{/if}>{$iss.shortLabel|escape}</option>
							{/foreach}
						</optgroup>
					{/if}
					{if !empty($backIssues)}
						<optgroup label="{translate key="editor.issues.backIssues"}">
							{foreach from=$backIssues key="issId" item="iss"}
								<option value="{$issId|escape}" {if $activeFilters.issueId == $issId}selected{/if}>{$iss.shortLabel|escape}</option>
							{/foreach}
						</optgroup>
					{/if}
				</select>

				<select name="sort" class="er-select er-auto-submit" aria-label="{translate key="plugins.generic.editorialReminderDashboard.filter.sort"}">
					<option value="urgency" {if $activeFilters.sort == 'urgency'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.sort.mostUrgent"}</option>
					<option value="due_asc" {if $activeFilters.sort == 'due_asc'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.sort.dueDateAsc"}</option>
					<option value="start_asc" {if $activeFilters.sort == 'start_asc'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.sort.startDateAsc"}</option>
					<option value="id_asc" {if $activeFilters.sort == 'id_asc'}selected{/if}>{translate key="plugins.generic.editorialReminderDashboard.sort.codeAsc"}</option>
				</select>
			</div>

			<div class="er-exports">
				<button type="button" class="er-btn er-btn--primary er-export-btn" data-format="xlsx">
					{translate key="plugins.generic.editorialReminderDashboard.export.excel"}
				</button>
				<button type="button" class="er-btn er-btn--outline er-export-btn" data-format="csv">
					{translate key="plugins.generic.editorialReminderDashboard.export.csv"}
				</button>
				<button type="button" class="er-btn er-btn--outline er-export-btn" data-format="pdf">
					{translate key="plugins.generic.editorialReminderDashboard.export.pdf"}
				</button>
			</div>
		</form>

		{* Articles Table *}
		<div class="er-table-wrapper">
			<table class="er-table">
				<thead>
					<tr>
						<th class="er-col-code">{translate key="plugins.generic.editorialReminderDashboard.table.code"}</th>
						<th class="er-col-title">{translate key="plugins.generic.editorialReminderDashboard.table.title"}</th>
						<th class="er-col-stage">{translate key="plugins.generic.editorialReminderDashboard.table.stage"}</th>
						<th class="er-col-editor">{translate key="plugins.generic.editorialReminderDashboard.table.editor"}</th>
						<th class="er-col-date">{translate key="plugins.generic.editorialReminderDashboard.table.startDate"}</th>
						<th class="er-col-date">{translate key="plugins.generic.editorialReminderDashboard.table.dueDate"}</th>
						<th class="er-col-remaining">{translate key="plugins.generic.editorialReminderDashboard.table.timeRemaining"}</th>
						<th class="er-col-status">{translate key="plugins.generic.editorialReminderDashboard.table.status"}</th>
					</tr>
				</thead>
				<tbody>
					{if empty($items)}
						<tr>
							<td colspan="8" class="er-empty-row">
								{translate key="plugins.generic.editorialReminderDashboard.table.empty"}
							</td>
						</tr>
					{else}
						{foreach from=$items item="item"}
							<tr class="er-row er-row--{$item.status|escape}" data-submission-id="{$item.id|escape}">
								<td class="er-col-code">
									<a href="{$workflowBaseUrl|escape}/{$item.id|escape}/{$item.stageId|escape}" class="er-code-link">
										{$item.id|escape}
									</a>
								</td>
								<td class="er-col-title">
									<div class="er-title-cell">
										<a href="{$workflowBaseUrl|escape}/{$item.id|escape}/{$item.stageId|escape}" class="er-article-link">
											{$item.title|escape}
										</a>
										<button type="button" class="er-expand-btn"
											aria-expanded="false"
											aria-controls="er-detail-{$item.id|escape}"
											data-target="er-detail-{$item.id|escape}"
											title="{translate key="plugins.generic.editorialReminderDashboard.action.toggleDetails"}">
											<span class="er-expand-btn__icon" aria-hidden="true">▾</span>
											<span class="er-sr-only">{translate key="plugins.generic.editorialReminderDashboard.action.toggleDetails"}</span>
										</button>
									</div>
									{if $item.delayCauseLabel && ($item.status == 'overdue' || $item.status == 'almost_due' || $item.status == 'no_target')}
										<div class="er-delay-cause-sub">
											{$item.delayCauseLabel|escape}
										</div>
									{/if}
								</td>
								<td class="er-col-stage">{$item.stageLabel|escape}</td>
								<td class="er-col-editor">{$item.editorNames|escape}</td>
								<td class="er-col-date">{$item.startDateFormatted|escape}</td>
								<td class="er-col-date">{$item.dueDateFormatted|escape}</td>
								<td class="er-col-remaining">
									{if $item.stageKey == 'copyediting' && $item.status == 'no_target' && $access.canEditTargetIssue}
										{if empty($allIssues)}
											<div class="er-no-issues-notice">
												<span class="er-remaining-text er-remaining-text--no_target">{translate key="plugins.generic.editorialReminderDashboard.status.noTarget"}</span>
												<a href="{$futureIssuesUrl|escape}" class="er-btn--link-inline" target="_blank" title="{translate key="plugins.generic.editorialReminderDashboard.copyediting.createFutureIssueHint"}">
													+ {translate key="plugins.generic.editorialReminderDashboard.copyediting.createFutureIssue"}
												</a>
											</div>
										{else}
											<select class="er-select er-select--inline er-target-issue-select"
												data-submission-id="{$item.id|escape}"
												aria-label="{translate key="plugins.generic.editorialReminderDashboard.copyediting.selectTargetIssue"}">
												<option value="none">{translate key="plugins.generic.editorialReminderDashboard.copyediting.selectTargetIssue"}</option>
												{if !empty($futureIssues)}
													<optgroup label="{translate key="editor.issues.futureIssues"}">
														{foreach from=$futureIssues key="issId" item="iss"}
															<option value="{$issId|escape}" {if $item.targetIssueId == $issId}selected{/if}>
																{$iss.shortLabel|escape} ({$iss.targetDate|escape})
															</option>
														{/foreach}
													</optgroup>
												{/if}
												{if !empty($backIssues)}
													<optgroup label="{translate key="editor.issues.backIssues"}">
														{foreach from=$backIssues key="issId" item="iss"}
															<option value="{$issId|escape}" {if $item.targetIssueId == $issId}selected{/if}>
																{$iss.shortLabel|escape} ({$iss.targetDate|escape})
															</option>
														{/foreach}
													</optgroup>
												{/if}
											</select>
										{/if}
									{else}
										<span class="er-remaining-text er-remaining-text--{$item.status|escape}">
											{$item.timeRemainingText|escape}
										</span>
									{/if}
								</td>
								<td class="er-col-status">
									<span class="er-badge er-badge--{$item.status|escape}">
										<span aria-hidden="true">{$item.statusSymbol|escape}</span>
										{$item.statusLabel|escape}
									</span>
								</td>
							</tr>

							{* Expandable Detail Row *}
							<tr id="er-detail-{$item.id|escape}" class="er-detail-row" hidden>
								<td colspan="8" class="er-detail-cell">
									<div class="er-detail-panel">
										<div class="er-detail-grid">
											{if $item.stageKey == 'review'}
												{* Reviewer Breakdown *}
												<div class="er-detail-section">
													<h3 class="er-detail-heading">
														{translate key="plugins.generic.editorialReminderDashboard.detail.reviewersHeading" round=$item.round}
													</h3>
													<p class="er-detail-meta">
														<strong>{translate key="plugins.generic.editorialReminderDashboard.table.delayCause"}:</strong>
														{$item.delayCauseLabel|escape}
													</p>
													{if empty($item.reviewers)}
														<p class="er-detail-empty">{translate key="plugins.generic.editorialReminderDashboard.delayCause.noReviewerAssigned"}</p>
													{else}
														<table class="er-subtable">
															<thead>
																<tr>
																	<th>{translate key="plugins.generic.editorialReminderDashboard.reviewer.name"}</th>
																	<th>{translate key="plugins.generic.editorialReminderDashboard.reviewer.dateAssigned"}</th>
																	<th>{translate key="plugins.generic.editorialReminderDashboard.reviewer.dateResponseDue"}</th>
																	<th>{translate key="plugins.generic.editorialReminderDashboard.reviewer.dateDue"}</th>
																	<th>{translate key="plugins.generic.editorialReminderDashboard.reviewer.status"}</th>
																	<th>{translate key="plugins.generic.editorialReminderDashboard.reviewer.ojsOverdue"}</th>
																</tr>
															</thead>
															<tbody>
																{foreach from=$item.reviewers item="rev"}
																	<tr>
																		<td>{$rev.reviewerName|escape}</td>
																		<td>{$rev.dateAssigned|escape}</td>
																		<td>{$rev.dateResponseDue|escape}</td>
																		<td>{$rev.dateDue|escape}</td>
																		<td>{$rev.statusLabel|escape}</td>
																		<td>
																			{if $rev.isOjsOverdue}
																				<span class="er-badge er-badge--overdue">{translate key="plugins.generic.editorialReminderDashboard.status.overdue"}</span>
																			{else}
																				—
																			{/if}
																		</td>
																	</tr>
																{/foreach}
															</tbody>
														</table>
													{/if}
												</div>
											{else}
												{* Copyediting Target Issue Management & Change Log *}
												<div class="er-detail-section">
													<h3 class="er-detail-heading">
														{translate key="plugins.generic.editorialReminderDashboard.copyediting.targetIssueHeading"}
													</h3>
													{if $access.canEditTargetIssue}
														<div class="er-inline-form">
															<label for="er-detail-issue-{$item.id|escape}">
																{translate key="plugins.generic.editorialReminderDashboard.copyediting.selectTargetIssue"}:
															</label>
															{if empty($allIssues)}
																<p class="er-detail-empty">
																	{translate key="plugins.generic.editorialReminderDashboard.copyediting.noUnpublishedIssuesHelp"}
																	<a href="{$futureIssuesUrl|escape}" target="_blank" class="er-link">{translate key="plugins.generic.editorialReminderDashboard.copyediting.openFutureIssues"}</a>
																</p>
															{else}
																<select id="er-detail-issue-{$item.id|escape}"
																	class="er-select er-target-issue-select"
																	data-submission-id="{$item.id|escape}">
																	<option value="none">— {translate key="plugins.generic.editorialReminderDashboard.status.noTarget"} —</option>
																	{if !empty($futureIssues)}
																		<optgroup label="{translate key="editor.issues.futureIssues"}">
																			{foreach from=$futureIssues key="issId" item="iss"}
																				<option value="{$issId|escape}" {if $item.targetIssueId == $issId}selected{/if}>
																					{$iss.identification|escape} (H: {$iss.targetDate|escape})
																				</option>
																			{/foreach}
																		</optgroup>
																	{/if}
																	{if !empty($backIssues)}
																		<optgroup label="{translate key="editor.issues.backIssues"}">
																			{foreach from=$backIssues key="issId" item="iss"}
																				<option value="{$issId|escape}" {if $item.targetIssueId == $issId}selected{/if}>
																					{$iss.identification|escape} (H: {$iss.targetDate|escape})
																				</option>
																			{/foreach}
																		</optgroup>
																	{/if}
																</select>
															{/if}
														</div>
													{/if}

													{if !empty($item.targetIssueLogs)}
														<h4 class="er-detail-subheading">
															{translate key="plugins.generic.editorialReminderDashboard.copyediting.changeLogHeading"}
														</h4>
														<ul class="er-log-list">
															{foreach from=$item.targetIssueLogs item="tlog"}
																<li>
																	<strong>{$tlog.dateChanged|escape}</strong> – {$tlog.changedByName|escape}:
																	{$tlog.oldIssueLabel|escape} → <strong>{$tlog.newIssueLabel|escape}</strong>
																</li>
															{/foreach}
														</ul>
													{/if}
												</div>
											{/if}

											{* Review Round History & Cumulative Duration *}
											<div class="er-detail-section">
												<h3 class="er-detail-heading">
													{translate key="plugins.generic.editorialReminderDashboard.roundHistory.heading"}
												</h3>
												<p class="er-detail-meta">
													<strong>{translate key="plugins.generic.editorialReminderDashboard.roundHistory.cumulativeDays"}:</strong>
													{$item.cumulativeWorkingDays|escape} {translate key="plugins.generic.editorialReminderDashboard.unit.workingDays"}
												</p>
												{if empty($item.roundHistory)}
													<p class="er-detail-empty">{translate key="plugins.generic.editorialReminderDashboard.roundHistory.empty"}</p>
												{else}
													<table class="er-subtable">
														<thead>
															<tr>
																<th>{translate key="plugins.generic.editorialReminderDashboard.roundHistory.round"}</th>
																<th>{translate key="plugins.generic.editorialReminderDashboard.table.startDate"}</th>
																<th>{translate key="plugins.generic.editorialReminderDashboard.roundHistory.endDate"}</th>
																<th>{translate key="plugins.generic.editorialReminderDashboard.roundHistory.duration"}</th>
																<th>{translate key="plugins.generic.editorialReminderDashboard.roundHistory.finalStatus"}</th>
															</tr>
														</thead>
														<tbody>
															{foreach from=$item.roundHistory item="rh"}
																<tr>
																	<td>{translate key="plugins.generic.editorialReminderDashboard.roundNumber" round=$rh.round}</td>
																	<td>{$rh.dateStart|escape}</td>
																	<td>{$rh.dateEnd|escape}</td>
																	<td>{$rh.workingDays|escape} {translate key="plugins.generic.editorialReminderDashboard.unit.workingDaysShort"}</td>
																	<td>{$rh.finalStatusLabel|escape}</td>
																</tr>
															{/foreach}
														</tbody>
													</table>
												{/if}
											</div>
										</div>
									</div>
								</td>
							</tr>
						{/foreach}
					{/if}
				</tbody>
			</table>
		</div>

		{* Footer note matching Page 1 Mockup *}
		<div class="er-footer-note">
			{translate key="plugins.generic.editorialReminderDashboard.footer.rulesSummary"
				round1Limit=$settings.round1Limit
				round2Limit=$settings.round2Limit
				copyeditingWarning=$settings.copyeditingWarning}
		</div>
	</div>

	{* Interactive Settings Modal *}
	<div id="erSettingsModal" class="er-modal" role="dialog" aria-modal="true" aria-labelledby="erSettingsModalTitle" hidden>
		<div class="er-modal__backdrop" id="erCloseSettingsBackdrop"></div>
		<div class="er-modal__content">
			<div class="er-modal__header">
				<h2 id="erSettingsModalTitle" class="er-modal__title">
					{translate key="plugins.generic.editorialReminderDashboard.settings.modalTitle"}
				</h2>
				<button type="button" class="er-modal__close" id="erCloseSettingsBtn" aria-label="{translate key="common.close"}">×</button>
			</div>

			<div class="er-modal__tabs" role="tablist">
				<button type="button" class="er-modal__tab er-modal__tab--active" data-modal-tab="rules">
					{translate key="plugins.generic.editorialReminderDashboard.settings.tabRules"}
				</button>
				{if $access.canManageSettings}
					<button type="button" class="er-modal__tab" data-modal-tab="issues">
						{translate key="plugins.generic.editorialReminderDashboard.settings.tabIssues"}
					</button>
					<button type="button" class="er-modal__tab" data-modal-tab="holidays">
						{translate key="plugins.generic.editorialReminderDashboard.settings.tabHolidays"}
					</button>
					<button type="button" class="er-modal__tab" data-modal-tab="audit">
						{translate key="plugins.generic.editorialReminderDashboard.settings.tabAudit"}
					</button>
				{/if}
			</div>

			<div class="er-modal__body">
				{* Tab 1: Rules & Notifications *}
				<div class="er-modal__pane" data-modal-pane="rules">
					<form id="erModalSettingsForm">
						{if !$access.canManageSettings}
							<input type="hidden" name="updateNotificationPrefOnly" value="1" />
						{else}
							<div class="er-form-grid">
								<div class="er-form-group">
									<label for="erRound1Limit">{translate key="plugins.generic.editorialReminderDashboard.settings.round1Limit"}</label>
									<input type="number" id="erRound1Limit" name="round1Limit" min="14" max="21" class="er-input" value="{$settings.round1Limit|escape}" />
									<small>{translate key="plugins.generic.editorialReminderDashboard.settings.round1LimitDesc"}</small>
								</div>
								<div class="er-form-group">
									<label for="erRound2Limit">{translate key="plugins.generic.editorialReminderDashboard.settings.round2Limit"}</label>
									<input type="number" id="erRound2Limit" name="round2Limit" min="1" max="60" class="er-input" value="{$settings.round2Limit|escape}" />
									<small>{translate key="plugins.generic.editorialReminderDashboard.settings.round2LimitDesc"}</small>
								</div>
								<div class="er-form-group">
									<label for="erRound1Warning">{translate key="plugins.generic.editorialReminderDashboard.settings.round1Warning"}</label>
									<input type="number" id="erRound1Warning" name="round1Warning" min="1" max="14" class="er-input" value="{$settings.round1Warning|escape}" />
								</div>
								<div class="er-form-group">
									<label for="erRound2Warning">{translate key="plugins.generic.editorialReminderDashboard.settings.round2Warning"}</label>
									<input type="number" id="erRound2Warning" name="round2Warning" min="1" max="14" class="er-input" value="{$settings.round2Warning|escape}" />
								</div>
								<div class="er-form-group">
									<label for="erCopyeditingWarning">{translate key="plugins.generic.editorialReminderDashboard.settings.copyeditingWarning"}</label>
									<input type="number" id="erCopyeditingWarning" name="copyeditingWarning" min="1" max="60" class="er-input" value="{$settings.copyeditingWarning|escape}" />
									<small>{translate key="plugins.generic.editorialReminderDashboard.settings.copyeditingWarningDesc"}</small>
								</div>
								<div class="er-form-group">
									<label for="erTimezone">{translate key="plugins.generic.editorialReminderDashboard.settings.timezone"}</label>
									<input type="text" id="erTimezone" name="timezone" class="er-input" value="{$settings.timezone|escape}" />
								</div>
							</div>

							<div class="er-form-group er-form-group--full">
								<label>{translate key="plugins.generic.editorialReminderDashboard.settings.workingDays"}</label>
								<div class="er-weekdays-grid">
									{foreach from=[1,2,3,4,5,6,7] item=dayNum}
										<label class="er-weekday-check">
											<input type="checkbox" name="workingDays[]" value="{$dayNum}" class="er-working-day-checkbox" {if !empty($settings.workingDaysMap[$dayNum])}checked{/if} />
											<span>{translate key="plugins.generic.editorialReminderDashboard.day.`$dayNum`"}</span>
										</label>
									{/foreach}
								</div>
								<small>{translate key="plugins.generic.editorialReminderDashboard.settings.workingDaysDesc"}</small>
							</div>

							<div class="er-form-checks">
								<label class="er-checkbox-label">
									<input type="checkbox" name="enableDailyDigest" value="1" {if $settings.enableDailyDigest}checked{/if} />
									{translate key="plugins.generic.editorialReminderDashboard.settings.enableDailyDigest"}
								</label>
								<label class="er-checkbox-label">
									<input type="checkbox" name="enableWeeklyDigest" value="1" {if $settings.enableWeeklyDigest}checked{/if} />
									{translate key="plugins.generic.editorialReminderDashboard.settings.enableWeeklyDigest"}
								</label>
							</div>
						{/if}

						<div class="er-form-checks">
							<label class="er-checkbox-label">
								<input type="checkbox" name="notificationOptOut" value="1" {if $userNotificationOptOut}checked{/if} />
								{translate key="plugins.generic.editorialReminderDashboard.settings.userOptOut"}
							</label>
						</div>

						<div class="er-modal__footer">
							<button type="submit" class="er-btn er-btn--primary">
								{translate key="common.save"}
							</button>
						</div>
					</form>
				</div>

				{if $access.canManageSettings}
					{* Tab 2: Issue Target Publication Dates H *}
					<div class="er-modal__pane" data-modal-pane="issues" hidden>
						<p class="er-modal__help">{translate key="plugins.generic.editorialReminderDashboard.settings.issueTargetHelp"}</p>
						{if empty($allIssues)}
							<p class="er-detail-empty">
								{translate key="plugins.generic.editorialReminderDashboard.settings.noUnpublishedIssues"}
								<a href="{$futureIssuesUrl|escape}" target="_blank" class="er-link">{translate key="plugins.generic.editorialReminderDashboard.copyediting.openFutureIssues"}</a>
							</p>
						{else}
							<table class="er-subtable">
								<thead>
									<tr>
										<th>{translate key="plugins.generic.editorialReminderDashboard.filter.issue"}</th>
										<th>{translate key="plugins.generic.editorialReminderDashboard.settings.targetDateH"}</th>
										<th>{translate key="common.status"}</th>
										<th>{translate key="common.action"}</th>
									</tr>
								</thead>
								<tbody>
									{foreach from=$allIssues key="issId" item="iss"}
										<tr>
											<td>{$iss.identification|escape}</td>
											<td>
												<input type="date" class="er-input er-issue-target-date-input"
													data-issue-id="{$issId|escape}"
													value="{$iss.targetDate|escape}" />
											</td>
											<td>
												{if $iss.published}
													<span class="er-role-pill">{translate key="editor.issues.backIssues"}</span>
												{else}
													<span class="er-role-pill er-role-pill--future">{translate key="editor.issues.futureIssues"}</span>
												{/if}
											</td>
											<td>
												<button type="button" class="er-btn er-btn--outline er-save-issue-date-btn" data-issue-id="{$issId|escape}">
													{translate key="common.save"}
												</button>
											</td>
										</tr>
									{/foreach}
								</tbody>
							</table>
						{/if}
					</div>

					{* Tab 3: Holidays & Collective Leave *}
					<div class="er-modal__pane" data-modal-pane="holidays" hidden>
						<p class="er-modal__help">{translate key="plugins.generic.editorialReminderDashboard.settings.holidayHelp"}</p>
						<form id="erAddHolidayForm" class="er-inline-form">
							<input type="date" name="holidayDate" class="er-input" required />
							<input type="text" name="description" class="er-input"
								placeholder="{translate key="plugins.generic.editorialReminderDashboard.settings.holidayDescriptionPlaceholder"}" required />
							{if $access.isSiteAdmin}
								<label class="er-checkbox-label">
									<input type="checkbox" name="isGlobal" value="1" checked />
									{translate key="plugins.generic.editorialReminderDashboard.settings.holidayGlobal"}
								</label>
							{/if}
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
											<td>
												{$hol.description|escape}
												{if $hol.contextId == 0}
													<span class="er-role-pill">{translate key="plugins.generic.editorialReminderDashboard.settings.holidayGlobalTag"}</span>
												{/if}
											</td>
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

					{* Tab 4: Audit Log *}
					<div class="er-modal__pane" data-modal-pane="audit" hidden>
						{if empty($auditLogs)}
							<p class="er-detail-empty">{translate key="common.noItemsFound"}</p>
						{else}
							<table class="er-subtable">
								<thead>
									<tr>
										<th>{translate key="common.date"}</th>
										<th>{translate key="common.user"}</th>
										<th>{translate key="common.action"}</th>
										<th>{translate key="plugins.generic.editorialReminderDashboard.audit.details"}</th>
									</tr>
								</thead>
								<tbody>
									{foreach from=$auditLogs item="alog"}
										<tr>
											<td>{$alog.createdAt|escape}</td>
											<td>{$alog.userName|escape}</td>
											<td>{$alog.action|escape}</td>
											<td><code>{$alog.details|escape}</code></td>
										</tr>
									{/foreach}
								</tbody>
							</table>
						{/if}
					</div>
				{/if}
			</div>
		</div>
	</div>
</div>
{/block}

