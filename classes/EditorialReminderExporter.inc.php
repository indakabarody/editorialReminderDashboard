<?php
/**
 * @file plugins/generic/editorialReminderDashboard/classes/EditorialReminderExporter.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderExporter
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Generates Excel (.xlsx multi-sheet), CSV (UTF-8 with BOM), and PDF 1.4 reports
 * for both Journal Dashboard and Site-level Publishing House Dashboard.
 */

class EditorialReminderExporter {

	/**
	 * Export Journal Dashboard data in the requested format ('xlsx', 'csv', 'pdf').
	 *
	 * @param string $format 'xlsx'|'csv'|'pdf'
	 * @param array $dashboardData Output of EditorialReminderService::getJournalDashboardData()
	 * @param array $metadata {reportTitle, journalName, exportedAt, activeFiltersText, exportedBy}
	 * @return void
	 */
	public function exportJournalReport($format, $dashboardData, $metadata) {
		$items = isset($dashboardData['items']) ? $dashboardData['items'] : [];
		$safeSlug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($metadata['journalName'] ?: 'journal'));
		$dateStamp = date('Ymd-His');

		// Sheet 1 rows: Metadata header + Article table
		$sheet1Rows = $this->_buildMetadataRows($metadata);
		$sheet1Rows[] = [];
		$sheet1Rows[] = [
			__('plugins.generic.editorialReminderDashboard.table.code'),
			__('plugins.generic.editorialReminderDashboard.table.title'),
			__('plugins.generic.editorialReminderDashboard.table.stage'),
			__('plugins.generic.editorialReminderDashboard.table.editor'),
			__('plugins.generic.editorialReminderDashboard.table.startDate'),
			__('plugins.generic.editorialReminderDashboard.table.dueDate'),
			__('plugins.generic.editorialReminderDashboard.table.timeRemaining'),
			__('plugins.generic.editorialReminderDashboard.table.status'),
			__('plugins.generic.editorialReminderDashboard.table.delayCause'),
			__('plugins.generic.editorialReminderDashboard.roundHistory.cumulativeDays'),
		];

		// Sheet 2 rows: Reviewer details
		$sheet2Rows = [
			[$metadata['reportTitle'] . ' - ' . __('plugins.generic.editorialReminderDashboard.export.reviewerSheetTitle')],
			[__('plugins.generic.editorialReminderDashboard.export.journal'), $metadata['journalName']],
			[__('plugins.generic.editorialReminderDashboard.export.exportedAt'), $metadata['exportedAt']],
			[],
			[
				__('plugins.generic.editorialReminderDashboard.table.code'),
				__('plugins.generic.editorialReminderDashboard.table.title'),
				__('plugins.generic.editorialReminderDashboard.table.stage'),
				__('plugins.generic.editorialReminderDashboard.reviewer.name'),
				__('plugins.generic.editorialReminderDashboard.reviewer.dateAssigned'),
				__('plugins.generic.editorialReminderDashboard.reviewer.dateResponseDue'),
				__('plugins.generic.editorialReminderDashboard.reviewer.dateDue'),
				__('plugins.generic.editorialReminderDashboard.reviewer.dateCompleted'),
				__('plugins.generic.editorialReminderDashboard.reviewer.status'),
				__('plugins.generic.editorialReminderDashboard.reviewer.ojsOverdue'),
			],
		];

		foreach ($items as $item) {
			$sheet1Rows[] = [
				(string) $item['id'],
				(string) $item['title'],
				(string) $item['stageLabel'],
				(string) $item['editorNames'],
				(string) ($item['startDate'] ?: '—'),
				(string) ($item['dueDate'] ?: '—'),
				(string) $item['timeRemainingText'],
				(string) ($item['statusSymbol'] . ' ' . $item['statusLabel']),
				(string) ($item['delayCauseLabel'] ?: '—'),
				(string) $item['cumulativeWorkingDays'],
			];

			if (!empty($item['reviewers'])) {
				foreach ($item['reviewers'] as $rev) {
					$sheet2Rows[] = [
						(string) $item['id'],
						(string) $item['title'],
						(string) $item['stageLabel'],
						(string) $rev['reviewerName'],
						(string) $rev['dateAssigned'],
						(string) $rev['dateResponseDue'],
						(string) $rev['dateDue'],
						(string) $rev['dateCompleted'],
						(string) $rev['statusLabel'],
						$rev['isOjsOverdue'] ? __('common.yes') : __('common.no'),
					];
				}
			}
		}

		$fmt = strtolower(trim((string) $format));
		if ($fmt === 'csv') {
			$this->_sendCsvDownload('editorial-reminder-' . $safeSlug . '-' . $dateStamp . '.csv', $sheet1Rows);
		} elseif ($fmt === 'pdf') {
			$this->_sendPdfDownload(
				'editorial-reminder-' . $safeSlug . '-' . $dateStamp . '.pdf',
				$metadata,
				$dashboardData['summary'],
				[
					__('plugins.generic.editorialReminderDashboard.table.code'),
					__('plugins.generic.editorialReminderDashboard.table.title'),
					__('plugins.generic.editorialReminderDashboard.table.stage'),
					__('plugins.generic.editorialReminderDashboard.table.editor'),
					__('plugins.generic.editorialReminderDashboard.table.startDate'),
					__('plugins.generic.editorialReminderDashboard.table.dueDate'),
					__('plugins.generic.editorialReminderDashboard.table.timeRemaining'),
					__('plugins.generic.editorialReminderDashboard.table.status'),
					__('plugins.generic.editorialReminderDashboard.table.delayCause'),
				],
				array_map(function ($it) {
					return [
						(string) $it['id'],
						$this->_truncate($it['title'], 48),
						(string) $it['stageLabel'],
						$this->_truncate($it['editorNames'], 22),
						(string) $it['startDateFormatted'],
						(string) $it['dueDateFormatted'],
						(string) $it['timeRemainingText'],
						(string) $it['statusLabel'],
						$this->_truncate($it['delayCauseLabel'] ?: '—', 26),
					];
				}, $items),
				[38, 175, 85, 85, 48, 48, 72, 68, 115]
			);
		} else {
			$sheets = [
				[
					'name' => __('plugins.generic.editorialReminderDashboard.export.articlesSheetTitle'),
					'rows' => $sheet1Rows,
					'headerRowIndex' => 6,
				],
				[
					'name' => __('plugins.generic.editorialReminderDashboard.export.reviewerSheetTitle'),
					'rows' => $sheet2Rows,
					'headerRowIndex' => 4,
				],
			];
			$this->_sendXlsxDownload('editorial-reminder-' . $safeSlug . '-' . $dateStamp . '.xlsx', $sheets);
		}
	}

	/**
	 * Export Site-level Publishing House report in 'xlsx', 'csv', or 'pdf' format.
	 *
	 * @param string $format 'xlsx'|'csv'|'pdf'
	 * @param array $siteData Output of EditorialReminderService::getSiteDashboardData()
	 * @param array $metadata {reportTitle, journalName, exportedAt, activeFiltersText, exportedBy}
	 * @return void
	 */
	public function exportSiteReport($format, $siteData, $metadata) {
		$dateStamp = date('Ymd-His');
		$journalRows = isset($siteData['journalRows']) ? $siteData['journalRows'] : [];
		$allItems = isset($siteData['allItems']) ? $siteData['allItems'] : [];

		$recapSheetRows = $this->_buildMetadataRows($metadata);
		$recapSheetRows[] = [];
		$recapSheetRows[] = [
			__('plugins.generic.editorialReminderDashboard.siteTable.journal'),
			__('plugins.generic.editorialReminderDashboard.siteTable.articles'),
			__('plugins.generic.editorialReminderDashboard.status.normal'),
			__('plugins.generic.editorialReminderDashboard.status.almostDue'),
			__('plugins.generic.editorialReminderDashboard.status.overdue'),
			__('plugins.generic.editorialReminderDashboard.status.noTarget'),
		];

		foreach ($journalRows as $jr) {
			$recapSheetRows[] = [
				(string) $jr['name'],
				(string) $jr['total'],
				(string) $jr['normal'],
				(string) $jr['almostDue'],
				(string) $jr['overdue'],
				(string) $jr['noTarget'],
			];
		}

		$detailSheetRows = $this->_buildMetadataRows($metadata);
		$detailSheetRows[] = [];
		$detailSheetRows[] = [
			__('plugins.generic.editorialReminderDashboard.siteTable.journal'),
			__('plugins.generic.editorialReminderDashboard.table.code'),
			__('plugins.generic.editorialReminderDashboard.table.title'),
			__('plugins.generic.editorialReminderDashboard.table.stage'),
			__('plugins.generic.editorialReminderDashboard.table.editor'),
			__('plugins.generic.editorialReminderDashboard.table.startDate'),
			__('plugins.generic.editorialReminderDashboard.table.dueDate'),
			__('plugins.generic.editorialReminderDashboard.table.timeRemaining'),
			__('plugins.generic.editorialReminderDashboard.table.status'),
			__('plugins.generic.editorialReminderDashboard.table.delayCause'),
		];

		foreach ($allItems as $item) {
			$detailSheetRows[] = [
				(string) $item['journalName'],
				(string) $item['id'],
				(string) $item['title'],
				(string) $item['stageLabel'],
				(string) $item['editorNames'],
				(string) ($item['startDate'] ?: '—'),
				(string) ($item['dueDate'] ?: '—'),
				(string) $item['timeRemainingText'],
				(string) ($item['statusSymbol'] . ' ' . $item['statusLabel']),
				(string) ($item['delayCauseLabel'] ?: '—'),
			];
		}

		$fmt = strtolower(trim((string) $format));
		if ($fmt === 'csv') {
			// All articles detail (CSV) for publishing house dashboard
			$this->_sendCsvDownload('editorial-reminder-all-journals-detail-' . $dateStamp . '.csv', $detailSheetRows);
		} elseif ($fmt === 'pdf') {
			$this->_sendPdfDownload(
				'editorial-reminder-site-recap-' . $dateStamp . '.pdf',
				$metadata,
				$siteData['summary'],
				[
					__('plugins.generic.editorialReminderDashboard.siteTable.journal'),
					__('plugins.generic.editorialReminderDashboard.siteTable.articles'),
					__('plugins.generic.editorialReminderDashboard.status.normal'),
					__('plugins.generic.editorialReminderDashboard.status.almostDue'),
					__('plugins.generic.editorialReminderDashboard.status.overdue'),
					__('plugins.generic.editorialReminderDashboard.status.noTarget'),
				],
				array_map(function ($jr) {
					return [
						$this->_truncate($jr['name'], 52),
						(string) $jr['total'],
						(string) $jr['normal'],
						(string) $jr['almostDue'],
						(string) $jr['overdue'],
						(string) $jr['noTarget'],
					];
				}, $journalRows),
				[260, 90, 95, 95, 95, 95]
			);
		} else {
			// Journal summary export (Excel) + Sheet 2 all articles detail
			$sheets = [
				[
					'name' => __('plugins.generic.editorialReminderDashboard.export.siteRecapSheetTitle'),
					'rows' => $recapSheetRows,
					'headerRowIndex' => 6,
				],
				[
					'name' => __('plugins.generic.editorialReminderDashboard.export.articlesSheetTitle'),
					'rows' => $detailSheetRows,
					'headerRowIndex' => 6,
				],
			];
			$this->_sendXlsxDownload('editorial-reminder-site-recap-' . $dateStamp . '.xlsx', $sheets);
		}
	}

	/**
	 * Build standard report metadata header rows.
	 *
	 * @param array $metadata
	 * @return array
	 */
	protected function _buildMetadataRows($metadata) {
		return [
			[__('plugins.generic.editorialReminderDashboard.export.reportTitle'), (string) $metadata['reportTitle']],
			[__('plugins.generic.editorialReminderDashboard.export.journal'), (string) $metadata['journalName']],
			[__('plugins.generic.editorialReminderDashboard.export.exportedAt'), (string) $metadata['exportedAt']],
			[__('plugins.generic.editorialReminderDashboard.export.activeFilters'), (string) $metadata['activeFiltersText']],
			[__('plugins.generic.editorialReminderDashboard.export.exportedBy'), (string) $metadata['exportedBy']],
		];
	}

	/**
	 * Output UTF-8 CSV with BOM for seamless opening in Excel and LibreOffice.
	 *
	 * @param string $filename
	 * @param array $rows
	 * @return void
	 */
	protected function _sendCsvDownload($filename, $rows) {
		if (!headers_sent()) {
			header('Content-Type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
		}

		$out = fopen('php://output', 'w');
		// Write UTF-8 BOM
		fwrite($out, "\xEF\xBB\xBF");
		foreach ($rows as $row) {
			fputcsv($out, $row);
		}
		fclose($out);
		exit;
	}

	/**
	 * Generate a standards-compliant multi-sheet OpenXML (.xlsx) file via ZipArchive.
	 *
	 * @param string $filename
	 * @param array $sheets Array of ['name' => string, 'rows' => array, 'headerRowIndex' => int]
	 * @return void
	 */
	protected function _sendXlsxDownload($filename, $sheets) {
		if (!class_exists('ZipArchive')) {
			// Fallback to CSV if ZipArchive is unavailable
			$firstRows = !empty($sheets[0]['rows']) ? $sheets[0]['rows'] : [];
			$this->_sendCsvDownload(preg_replace('/\.xlsx$/i', '.csv', $filename), $firstRows);
			return;
		}

		$tmpFile = tempnam(sys_get_temp_dir(), 'er_xlsx_');
		$zip = new ZipArchive();
		if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
			$firstRows = !empty($sheets[0]['rows']) ? $sheets[0]['rows'] : [];
			$this->_sendCsvDownload(preg_replace('/\.xlsx$/i', '.csv', $filename), $firstRows);
			return;
		}

		// 1. [Content_Types].xml
		$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

		foreach ($sheets as $idx => $sh) {
			$sheetNum = $idx + 1;
			$contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $sheetNum . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		$contentTypes .= '</Types>';
		$zip->addFromString('[Content_Types].xml', $contentTypes);

		// 2. _rels/.rels
		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
		$zip->addFromString('_rels/.rels', $rels);

		// 3. xl/workbook.xml & xl/_rels/workbook.xml.rels
		$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets>';
		$wbRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

		foreach ($sheets as $idx => $sh) {
			$sheetNum = $idx + 1;
			$safeSheetName = mb_substr(preg_replace('/[\[\]\*\/\\\\\?\:]+/', ' ', $sh['name']), 0, 31);
			if ($safeSheetName === '') {
				$safeSheetName = 'Sheet' . $sheetNum;
			}
			$workbookXml .= '<sheet name="' . $this->_xmlEscape($safeSheetName) . '" sheetId="' . $sheetNum . '" r:id="rId' . $sheetNum . '"/>';
			$wbRelsXml .= '<Relationship Id="rId' . $sheetNum . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>';
		}
		$styleRelId = count($sheets) + 1;
		$workbookXml .= '</sheets></workbook>';
		$wbRelsXml .= '<Relationship Id="rId' . $styleRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';

		$zip->addFromString('xl/workbook.xml', $workbookXml);
		$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRelsXml);

		// 4. xl/styles.xml (Style 0 = normal, Style 1 = bold metadata label, Style 2 = dark navy table header)
		$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="3">'
			. '<font><sz val="10"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="10"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="3">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FF0F3443"/><bgColor indexed="64"/></patternFill></fill>'
			. '</fills>'
			. '<borders count="2">'
			. '<border><left/><right/><top/><bottom/><diagonal/></border>'
			. '<border>'
			. '<left style="thin"><color rgb="FFD1D5DB"/></left>'
			. '<right style="thin"><color rgb="FFD1D5DB"/></right>'
			. '<top style="thin"><color rgb="FFD1D5DB"/></top>'
			. '<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>'
			. '<diagonal/>'
			. '</border>'
			. '</borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="4">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
			. '</cellXfs>'
			. '</styleSheet>';
		$zip->addFromString('xl/styles.xml', $stylesXml);

		// 5. Worksheets
		foreach ($sheets as $idx => $sh) {
			$sheetNum = $idx + 1;
			$headerIdx = isset($sh['headerRowIndex']) ? (int) $sh['headerRowIndex'] : 6;
			$wsXml = $this->_buildWorksheetXml($sh['rows'], $headerIdx);
			$zip->addFromString('xl/worksheets/sheet' . $sheetNum . '.xml', $wsXml);
		}

		$zip->close();

		if (!headers_sent()) {
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
			header('Content-Length: ' . filesize($tmpFile));
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
		}

		readfile($tmpFile);
		@unlink($tmpFile);
		exit;
	}

	/**
	 * Build OpenXML worksheet content.
	 *
	 * @param array $rows
	 * @param int $headerRowIndex 0-based row index of the table header
	 * @return string
	 */
	protected function _buildWorksheetXml($rows, $headerRowIndex = 6) {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<cols>'
			. '<col min="1" max="1" width="16" customWidth="1"/>'
			. '<col min="2" max="2" width="46" customWidth="1"/>'
			. '<col min="3" max="10" width="22" customWidth="1"/>'
			. '</cols>'
			. '<sheetData>';

		foreach ($rows as $rIdx => $row) {
			$excelRowNum = $rIdx + 1;
			$xml .= '<row r="' . $excelRowNum . '">';
			foreach (array_values($row) as $cIdx => $cellVal) {
				$colRef = $this->_colIndexToLetter($cIdx) . $excelRowNum;
				$styleId = 0;
				if ($rIdx < $headerRowIndex && $cIdx === 0) {
					$styleId = 1; // Bold metadata label
				} elseif ($rIdx === $headerRowIndex) {
					$styleId = 2; // Table header
				} elseif ($rIdx > $headerRowIndex) {
					$styleId = 3; // Bordered data cell
				}

				$valStr = (string) $cellVal;
				if (ctype_digit($valStr) && strlen($valStr) < 10) {
					$xml .= '<c r="' . $colRef . '" s="' . $styleId . '" t="n"><v>' . $valStr . '</v></c>';
				} else {
					$xml .= '<c r="' . $colRef . '" s="' . $styleId . '" t="inlineStr"><is><t xml:space="preserve">'
						. $this->_xmlEscape($valStr)
						. '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}

		$xml .= '</sheetData></worksheet>';
		return $xml;
	}

	/**
	 * Generate a standalone PDF 1.4 Landscape A4 report with metadata header, summary, and table.
	 *
	 * @param string $filename
	 * @param array $metadata
	 * @param array $summary
	 * @param array $headers
	 * @param array $rows
	 * @param array $colWidths
	 * @return void
	 */
	protected function _sendPdfDownload($filename, $metadata, $summary, $headers, $rows, $colWidths) {
		$pdfBytes = $this->_generatePdfDocument($metadata, $summary, $headers, $rows, $colWidths);

		if (!headers_sent()) {
			header('Content-Type: application/pdf');
			header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
			header('Content-Length: ' . strlen($pdfBytes));
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
		}

		echo $pdfBytes;
		exit;
	}

	/**
	 * Build valid PDF 1.4 binary string (A4 Landscape: 842 x 595 pt).
	 *
	 * @param array $metadata
	 * @param array $summary
	 * @param array $headers
	 * @param array $rows
	 * @param array $colWidths
	 * @return string
	 */
	protected function _generatePdfDocument($metadata, $summary, $headers, $rows, $colWidths) {
		$pageWidth = 842;
		$pageHeight = 595;
		$marginLeft = 36;
		$rowHeight = 18;
		$rowsPerFirstPage = 19;
		$rowsPerSubsequentPage = 25;

		// Split rows into pages
		$pages = [];
		if (empty($rows)) {
			$pages[] = [];
		} else {
			$pages[] = array_slice($rows, 0, $rowsPerFirstPage);
			$remaining = array_slice($rows, $rowsPerFirstPage);
			while (!empty($remaining)) {
				$pages[] = array_slice($remaining, 0, $rowsPerSubsequentPage);
				$remaining = array_slice($remaining, $rowsPerSubsequentPage);
			}
		}

		$pageStreams = [];
		$totalPages = count($pages);

		foreach ($pages as $pIdx => $pageRows) {
			$ops = [];
			$y = $pageHeight - 38;

			// Top Banner
			$ops[] = "0.06 0.20 0.26 rg";
			$ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - 24, $pageWidth - ($marginLeft * 2), 30);
			$ops[] = "1 1 1 rg";
			$ops[] = $this->_pdfText($marginLeft + 10, $y - 13, $metadata['reportTitle'] . ' - ' . $metadata['journalName'], 'F2', 12);
			$ops[] = $this->_pdfText($pageWidth - 130, $y - 13, __('plugins.generic.editorialReminderDashboard.export.page') . ' ' . ($pIdx + 1) . ' / ' . $totalPages, 'F1', 9);
			$y -= 40;

			if ($pIdx === 0) {
				// Metadata Box
				$ops[] = "0.96 0.97 0.98 rg";
				$ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - 46, $pageWidth - ($marginLeft * 2), 50);
				$ops[] = "0.15 0.18 0.22 rg";
				$ops[] = $this->_pdfText($marginLeft + 8, $y - 12, __('plugins.generic.editorialReminderDashboard.export.exportedAt') . ': ' . $metadata['exportedAt'], 'F1', 8.5);
				$ops[] = $this->_pdfText($marginLeft + 290, $y - 12, __('plugins.generic.editorialReminderDashboard.export.exportedBy') . ': ' . $metadata['exportedBy'], 'F1', 8.5);
				$ops[] = $this->_pdfText($marginLeft + 8, $y - 26, __('plugins.generic.editorialReminderDashboard.export.activeFilters') . ': ' . $metadata['activeFiltersText'], 'F1', 8.5);

				$summaryLine = sprintf(
					"%s: %d   |   %s: %d   |   %s: %d   |   %s: %d   |   %s: %d",
					__('plugins.generic.editorialReminderDashboard.export.total'),
					isset($summary['total']) ? (int) $summary['total'] : 0,
					__('plugins.generic.editorialReminderDashboard.status.normal'),
					isset($summary['normal']) ? (int) $summary['normal'] : 0,
					__('plugins.generic.editorialReminderDashboard.status.almostDue'),
					isset($summary['almostDue']) ? (int) $summary['almostDue'] : 0,
					__('plugins.generic.editorialReminderDashboard.status.overdue'),
					isset($summary['overdue']) ? (int) $summary['overdue'] : 0,
					__('plugins.generic.editorialReminderDashboard.status.noTarget'),
					isset($summary['noTarget']) ? (int) $summary['noTarget'] : 0
				);
				$ops[] = $this->_pdfText($marginLeft + 8, $y - 40, $summaryLine, 'F2', 8.5);
				$y -= 58;
			}

			// Table Header
			$tableWidth = array_sum($colWidths);
			$ops[] = "0.12 0.25 0.32 rg";
			$ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - $rowHeight, $tableWidth, $rowHeight);
			$ops[] = "1 1 1 rg";
			$cx = $marginLeft;
			foreach ($headers as $hIdx => $hText) {
				$ops[] = $this->_pdfText($cx + 4, $y - 12, $hText, 'F2', 8);
				$cx += isset($colWidths[$hIdx]) ? $colWidths[$hIdx] : 80;
			}
			$y -= $rowHeight;

			// Table Rows
			if (empty($pageRows)) {
				$ops[] = "0.35 0.38 0.42 rg";
				$ops[] = $this->_pdfText($marginLeft + 8, $y - 14, __('common.noItemsFound'), 'F1', 9);
			} else {
				foreach ($pageRows as $rIdx => $rCells) {
					if ($rIdx % 2 === 1) {
						$ops[] = "0.97 0.98 0.99 rg";
						$ops[] = sprintf("%d %d %d %d re f", $marginLeft, $y - $rowHeight, $tableWidth, $rowHeight);
					}
					// Row bottom border
					$ops[] = "0.86 0.88 0.90 RG 0.5 w";
					$ops[] = sprintf("%d %d m %d %d l S", $marginLeft, $y - $rowHeight, $marginLeft + $tableWidth, $y - $rowHeight);

					$ops[] = "0.12 0.14 0.17 rg";
					$cx = $marginLeft;
					foreach ($rCells as $cIdx => $cText) {
						$ops[] = $this->_pdfText($cx + 4, $y - 12, (string) $cText, 'F1', 7.8);
						$cx += isset($colWidths[$cIdx]) ? $colWidths[$cIdx] : 80;
					}
					$y -= $rowHeight;
				}
			}

			// Footer
			$ops[] = "0.45 0.48 0.52 rg";
			$ops[] = $this->_pdfText($marginLeft, 20, 'Editorial Reminder Dashboard - OJS 3.3 Plugin | Copyright (c) 2026 Indaka Barody', 'F1', 7.5);

			$pageStreams[] = implode("\n", $ops);
		}

		// Assemble PDF objects
		// 1: Catalog, 2: Pages, 3: Font Helvetica (F1), 4: Font Helvetica-Bold (F2)
		// 5..N: Page & Content objects
		$objects = [];
		$objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
		$objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
		$objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

		$kids = [];
		$nextObjId = 5;
		foreach ($pageStreams as $streamData) {
			$pageObjId = $nextObjId++;
			$contentObjId = $nextObjId++;
			$kids[] = $pageObjId . " 0 R";

			$objects[$pageObjId] = sprintf(
				"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
				$pageWidth,
				$pageHeight,
				$contentObjId
			);
			$objects[$contentObjId] = sprintf(
				"<< /Length %d >>\nstream\n%s\nendstream",
				strlen($streamData),
				$streamData
			);
		}

		$objects[2] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $kids), count($kids));
		ksort($objects);

		$pdf = "%PDF-1.4\n";
		$offsets = [];
		foreach ($objects as $id => $body) {
			$offsets[$id] = strlen($pdf);
			$pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xrefOffset = strlen($pdf);
		$totalObj = count($objects) + 1;
		$pdf .= "xref\n0 " . $totalObj . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ($i = 1; $i < $totalObj; $i++) {
			$pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
		}
		$pdf .= "trailer\n<< /Size " . $totalObj . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

		return $pdf;
	}

	/**
	 * Format a PDF text drawing command with sanitized WinAnsi text.
	 *
	 * @param float $x
	 * @param float $y
	 * @param string $text
	 * @param string $fontRef
	 * @param float $size
	 * @return string
	 */
	protected function _pdfText($x, $y, $text, $fontRef = 'F1', $size = 9) {
		$clean = str_replace(
			['—', '–', '·', '●', '▲', '■', '◆'],
			['-', '-', '-', '*', '!', '#', '?'],
			(string) $text
		);
		if (function_exists('iconv')) {
			$converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $clean);
			if ($converted !== false) {
				$clean = $converted;
			}
		}
		$escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $clean);
		return sprintf("BT /%s %.1f Tf %.1f %.1f Td (%s) Tj ET", $fontRef, $size, $x, $y, $escaped);
	}

	/**
	 * Convert 0-based column index to Excel column letter (A, B, ..., Z, AA...).
	 *
	 * @param int $index
	 * @return string
	 */
	protected function _colIndexToLetter($index) {
		$n = (int) $index;
		$letter = '';
		while ($n >= 0) {
			$letter = chr(($n % 26) + 65) . $letter;
			$n = (int) floor($n / 26) - 1;
		}
		return $letter;
	}

	/**
	 * Escape string for XML inclusion.
	 *
	 * @param string $str
	 * @return string
	 */
	protected function _xmlEscape($str) {
		$clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $str);
		return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Truncate text for PDF cells.
	 *
	 * @param string $str
	 * @param int $maxLen
	 * @return string
	 */
	protected function _truncate($str, $maxLen = 40) {
		$s = trim((string) $str);
		if (mb_strlen($s) <= $maxLen) {
			return $s;
		}
		return mb_substr($s, 0, $maxLen - 3) . '...';
	}
}

