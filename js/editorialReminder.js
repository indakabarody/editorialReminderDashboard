/**
 * @file plugins/generic/editorialReminderDashboard/js/editorialReminder.js
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Client-side interactivity for the Editorial Reminder Dashboard plugin.
 */

(function () {
	'use strict';

	function initEditorialReminderDashboard() {
		var root = document.getElementById('erJournalDashboard') || document.getElementById('erSiteDashboard');
		if (!root) {
			return;
		}

		var csrfToken = root.getAttribute('data-csrf-token') || '';
		var exportBaseUrl = root.getAttribute('data-export-url') || '';
		var saveTargetUrl = root.getAttribute('data-save-target-url') || '';
		var saveIssueDateUrl = root.getAttribute('data-save-issue-date-url') || '';
		var saveHolidayUrl = root.getAttribute('data-save-holiday-url') || '';
		var deleteHolidayUrl = root.getAttribute('data-delete-holiday-url') || '';
		var saveSettingsUrl = root.getAttribute('data-save-settings-url') || '';

		var filterForm = document.getElementById('erFilterForm');
		var stageInput = document.getElementById('erStageInput');

		// 1. Stage tabs (All / Review / Copyediting)
		var stageTabs = root.querySelectorAll('.er-tab[data-stage]');
		stageTabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				if (stageInput && filterForm) {
					stageInput.value = tab.getAttribute('data-stage') || 'all';
					filterForm.submit();
				}
			});
		});

		// 2. Auto-submit filter selects
		var autoSelects = root.querySelectorAll('.er-auto-submit');
		autoSelects.forEach(function (sel) {
			sel.addEventListener('change', function () {
				if (filterForm) {
					filterForm.submit();
				}
			});
		});

		// 3. Export buttons carrying active filter parameters
		var exportBtns = root.querySelectorAll('.er-export-btn');
		exportBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!exportBaseUrl) {
					return;
				}
				var format = btn.getAttribute('data-format') || 'xlsx';
				var params = new URLSearchParams();
				if (filterForm) {
					var formData = new FormData(filterForm);
					formData.forEach(function (val, key) {
						if (val !== null && String(val).trim() !== '') {
							params.set(key, String(val));
						}
					});
				}
				params.set('format', format);
				var sep = exportBaseUrl.indexOf('?') === -1 ? '?' : '&';
				window.location.href = exportBaseUrl + sep + params.toString();
			});
		});

		// 4. Expandable detail rows
		var expandBtns = root.querySelectorAll('.er-expand-btn');
		expandBtns.forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.stopPropagation();
				var targetId = btn.getAttribute('data-target');
				var detailRow = targetId ? document.getElementById(targetId) : null;
				if (!detailRow) {
					return;
				}
				var isHidden = detailRow.hasAttribute('hidden');
				if (isHidden) {
					detailRow.removeAttribute('hidden');
					btn.setAttribute('aria-expanded', 'true');
				} else {
					detailRow.setAttribute('hidden', 'hidden');
					btn.setAttribute('aria-expanded', 'false');
				}
			});
		});

		// 5. Target Issue dropdown change
		var targetIssueSelects = root.querySelectorAll('.er-target-issue-select');
		targetIssueSelects.forEach(function (sel) {
			sel.addEventListener('change', function () {
				if (!saveTargetUrl) {
					return;
				}
				var submissionId = sel.getAttribute('data-submission-id');
				var issueId = sel.value;
				sel.disabled = true;

				postFormUrlEncoded(saveTargetUrl, {
					csrfToken: csrfToken,
					submissionId: submissionId,
					issueId: issueId
				}).then(function (res) {
					if (res && res.ok) {
						window.location.reload();
					} else {
						sel.disabled = false;
						alert((res && res.message) || 'Failed to update Target Issue.');
					}
				}).catch(function () {
					sel.disabled = false;
				});
			});
		});

		// 6. Settings Modal controls
		var modal = document.getElementById('erSettingsModal');
		var openModalBtn = document.getElementById('erOpenSettingsBtn');
		var closeModalBtn = document.getElementById('erCloseSettingsBtn');
		var closeBackdrop = document.getElementById('erCloseSettingsBackdrop');

		function openModal() {
			if (modal) {
				modal.removeAttribute('hidden');
			}
		}

		function closeModal() {
			if (modal) {
				modal.setAttribute('hidden', 'hidden');
			}
		}

		if (openModalBtn) {
			openModalBtn.addEventListener('click', openModal);
		}
		if (closeModalBtn) {
			closeModalBtn.addEventListener('click', closeModal);
		}
		if (closeBackdrop) {
			closeBackdrop.addEventListener('click', closeModal);
		}

		document.addEventListener('keydown', function (ev) {
			if (ev.key === 'Escape' && modal && !modal.hasAttribute('hidden')) {
				closeModal();
			}
		});

		// Modal tab switching
		if (modal) {
			var modalTabs = modal.querySelectorAll('.er-modal__tab[data-modal-tab]');
			var modalPanes = modal.querySelectorAll('.er-modal__pane[data-modal-pane]');

			modalTabs.forEach(function (mTab) {
				mTab.addEventListener('click', function () {
					var targetPane = mTab.getAttribute('data-modal-tab');
					modalTabs.forEach(function (t) {
						t.classList.toggle('er-modal__tab--active', t === mTab);
					});
					modalPanes.forEach(function (pane) {
						if (pane.getAttribute('data-modal-pane') === targetPane) {
							pane.removeAttribute('hidden');
						} else {
							pane.setAttribute('hidden', 'hidden');
						}
					});
				});
			});
		}

		// 7. Save Settings form inside modal
		var modalSettingsForm = document.getElementById('erModalSettingsForm');
		if (modalSettingsForm && saveSettingsUrl) {
			modalSettingsForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var fd = new FormData(modalSettingsForm);
				var payload = { csrfToken: csrfToken };
				fd.forEach(function (v, k) {
					if (k !== 'workingDays[]') {
						payload[k] = v;
					}
				});

				var dayCheckboxes = modalSettingsForm.querySelectorAll('.er-working-day-checkbox');
				if (dayCheckboxes.length > 0) {
					var selectedDays = [];
					dayCheckboxes.forEach(function (cb) {
						if (cb.checked) {
							selectedDays.push(cb.value);
						}
					});
					if (selectedDays.length === 0) {
						alert(root.getAttribute('data-min-working-day-msg') || 'Minimal pilih 1 hari kerja aktif.');
						return;
					}
					payload.workingDays = selectedDays.join(',');
				}

				postFormUrlEncoded(saveSettingsUrl, payload).then(function (res) {
					if (res && res.ok) {
						window.location.reload();
					} else {
						alert((res && res.message) || 'Error saving settings.');
					}
				});
			});
		}

		// 8. Save Issue Target Date H
		var saveIssueDateBtns = root.querySelectorAll('.er-save-issue-date-btn');
		saveIssueDateBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!saveIssueDateUrl) {
					return;
				}
				var issueId = btn.getAttribute('data-issue-id');
				var dateInput = root.querySelector('.er-issue-target-date-input[data-issue-id="' + issueId + '"]');
				if (!dateInput || !dateInput.value) {
					return;
				}
				btn.disabled = true;
				postFormUrlEncoded(saveIssueDateUrl, {
					csrfToken: csrfToken,
					issueId: issueId,
					targetDate: dateInput.value
				}).then(function (res) {
					btn.disabled = false;
					if (res && res.ok) {
						window.location.reload();
					} else {
						alert((res && res.message) || 'Failed to save issue target date.');
					}
				});
			});
		});

		// 9. Add & Delete Holiday
		var addHolidayForm = document.getElementById('erAddHolidayForm');
		if (addHolidayForm && saveHolidayUrl) {
			addHolidayForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var fd = new FormData(addHolidayForm);
				var payload = { csrfToken: csrfToken };
				fd.forEach(function (v, k) {
					payload[k] = v;
				});
				postFormUrlEncoded(saveHolidayUrl, payload).then(function (res) {
					if (res && res.ok) {
						window.location.reload();
					} else {
						alert((res && res.message) || 'Failed to add holiday.');
					}
				});
			});
		}

		var deleteHolidayBtns = root.querySelectorAll('.er-delete-holiday-btn');
		deleteHolidayBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!deleteHolidayUrl) {
					return;
				}
				var holidayId = btn.getAttribute('data-holiday-id');
				postFormUrlEncoded(deleteHolidayUrl, {
					csrfToken: csrfToken,
					holidayId: holidayId
				}).then(function (res) {
					if (res && res.ok) {
						window.location.reload();
					}
				});
			});
		});
	}

	function postFormUrlEncoded(url, data) {
		var params = new URLSearchParams();
		Object.keys(data).forEach(function (k) {
			if (data[k] !== undefined && data[k] !== null) {
				params.append(k, String(data[k]));
			}
		});
		return fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: params.toString(),
			credentials: 'same-origin'
		}).then(function (r) {
			return r.json();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initEditorialReminderDashboard);
	} else {
		initEditorialReminderDashboard();
	}
})();

