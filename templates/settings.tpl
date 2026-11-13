{**
 * templates/settings.tpl
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Settings form for the Editorial Reminder Dashboard plugin.
 *}
<script>
	$(function() {ldelim}
		$('#editorialReminderSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="editorialReminderSettingsForm"
	method="POST"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	{csrf}

	{fbvFormArea id="editorialReminderSettingsArea"}
		{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.round1Limit"}
			{fbvElement
				type="text"
				id="round1Limit"
				value=$round1Limit
				description="plugins.generic.editorialReminderDashboard.settings.round1LimitDesc"
				size=$fbvStyles.size.SMALL
			}
		{/fbvFormSection}

		{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.round2Limit"}
			{fbvElement
				type="text"
				id="round2Limit"
				value=$round2Limit
				description="plugins.generic.editorialReminderDashboard.settings.round2LimitDesc"
				size=$fbvStyles.size.SMALL
			}
		{/fbvFormSection}

		{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.round1Warning"}
			{fbvElement
				type="text"
				id="round1Warning"
				value=$round1Warning
				size=$fbvStyles.size.SMALL
			}
		{/fbvFormSection}

		{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.round2Warning"}
			{fbvElement
				type="text"
				id="round2Warning"
				value=$round2Warning
				size=$fbvStyles.size.SMALL
			}
		{/fbvFormSection}

		{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.copyeditingWarning"}
			{fbvElement
				type="text"
				id="copyeditingWarning"
				value=$copyeditingWarning
				description="plugins.generic.editorialReminderDashboard.settings.copyeditingWarningDesc"
				size=$fbvStyles.size.SMALL
			}
		{/fbvFormSection}

		{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.timezone"}
			{fbvElement
				type="text"
				id="timezone"
				value=$timezone
				size=$fbvStyles.size.MEDIUM
			}
		{/fbvFormSection}

		{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.workingDays" description="plugins.generic.editorialReminderDashboard.settings.workingDaysDesc" list=true}
			{foreach from=[1,2,3,4,5,6,7] item=dayNum}
				{fbvElement
					type="checkbox"
					name="workingDays[]"
					id="workingDays_`$dayNum`"
					value=$dayNum
					checked=!empty($workingDaysMap[$dayNum])
					label="plugins.generic.editorialReminderDashboard.day.`$dayNum`"
				}
			{/foreach}
		{/fbvFormSection}

		{if $isSiteAdmin}
			{fbvFormSection label="plugins.generic.editorialReminderDashboard.settings.directorUsernames"}
				{fbvElement
					type="text"
					id="directorUsernames"
					value=$directorUsernames
					description="plugins.generic.editorialReminderDashboard.settings.directorUsernamesDesc"
				}
			{/fbvFormSection}
		{/if}

		{fbvFormSection list=true}
			{fbvElement
				type="checkbox"
				id="enableDailyDigest"
				checked=$enableDailyDigest
				label="plugins.generic.editorialReminderDashboard.settings.enableDailyDigest"
			}
			{fbvElement
				type="checkbox"
				id="enableWeeklyDigest"
				checked=$enableWeeklyDigest
				label="plugins.generic.editorialReminderDashboard.settings.enableWeeklyDigest"
			}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
