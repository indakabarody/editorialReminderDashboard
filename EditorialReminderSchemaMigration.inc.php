<?php
/**
 * @file plugins/generic/editorialReminderDashboard/EditorialReminderSchemaMigration.inc.php
 *
 * Copyright (c) 2026 Indaka Barody
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminderSchemaMigration
 * @ingroup plugins_generic_editorialReminderDashboard
 *
 * @brief Describe database table structures for the Editorial Reminder Dashboard plugin.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class EditorialReminderSchemaMigration extends Migration
{
    /**
     * Run the migrations to create plugin-specific tables without touching core OJS tables.
     *
     * @return void
     */
    public function up()
    {
        // 1. Article target issue mapping for Copyediting stage
        if (!Capsule::schema()->hasTable('editorial_reminder_target_issues')) {
            Capsule::schema()->create('editorial_reminder_target_issues', function (Blueprint $table) {
                $table->bigInteger('target_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->bigInteger('issue_id')->nullable();
                $table->date('target_date')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->datetime('date_updated')->nullable();
                $table->unique(['submission_id'], 'er_target_issues_submission_unique');
                $table->index(['context_id'], 'er_target_issues_context_id');
                $table->index(['issue_id'], 'er_target_issues_issue_id');
            });
        }

        // 2. Audit history of Target Issue changes on submissions
        if (!Capsule::schema()->hasTable('editorial_reminder_target_issue_logs')) {
            Capsule::schema()->create('editorial_reminder_target_issue_logs', function (Blueprint $table) {
                $table->bigInteger('log_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->bigInteger('old_issue_id')->nullable();
                $table->bigInteger('new_issue_id')->nullable();
                $table->bigInteger('changed_by');
                $table->datetime('date_changed');
                $table->index(['submission_id'], 'er_target_logs_submission_id');
                $table->index(['context_id'], 'er_target_logs_context_id');
            });
        }

        // 3. Target publication date (H) per issue
        if (!Capsule::schema()->hasTable('editorial_reminder_issue_targets')) {
            Capsule::schema()->create('editorial_reminder_issue_targets', function (Blueprint $table) {
                $table->bigInteger('issue_target_id')->autoIncrement();
                $table->bigInteger('issue_id');
                $table->bigInteger('context_id');
                $table->date('target_date');
                $table->bigInteger('updated_by')->nullable();
                $table->datetime('date_updated')->nullable();
                $table->unique(['issue_id'], 'er_issue_targets_issue_unique');
                $table->index(['context_id'], 'er_issue_targets_context_id');
            });
        }

        // 4. National holidays and collective leave dates
        if (!Capsule::schema()->hasTable('editorial_reminder_holidays')) {
            Capsule::schema()->create('editorial_reminder_holidays', function (Blueprint $table) {
                $table->bigInteger('holiday_id')->autoIncrement();
                $table->bigInteger('context_id')->default(0);
                $table->date('holiday_date');
                $table->string('description', 255);
                $table->datetime('created_at')->nullable();
                $table->index(['context_id', 'holiday_date'], 'er_holidays_context_date');
                $table->unique(['context_id', 'holiday_date'], 'er_holidays_unique');
            });
        }

        // 5. Completed review round history snapshots
        if (!Capsule::schema()->hasTable('editorial_reminder_round_history')) {
            Capsule::schema()->create('editorial_reminder_round_history', function (Blueprint $table) {
                $table->bigInteger('history_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->bigInteger('review_round_id')->nullable();
                $table->smallInteger('stage_id')->default(3);
                $table->smallInteger('round');
                $table->date('date_start');
                $table->date('date_end')->nullable();
                $table->integer('working_days')->default(0);
                $table->string('final_status', 64)->nullable();
                $table->unique(['submission_id', 'stage_id', 'round'], 'er_round_history_unique');
                $table->index(['submission_id'], 'er_round_history_submission_id');
                $table->index(['context_id'], 'er_round_history_context_id');
            });
        }

        // 6. General audit log for settings & target issue changes (Section 10 Audit Trail)
        if (!Capsule::schema()->hasTable('editorial_reminder_audit_logs')) {
            Capsule::schema()->create('editorial_reminder_audit_logs', function (Blueprint $table) {
                $table->bigInteger('audit_id')->autoIncrement();
                $table->bigInteger('context_id')->default(0);
                $table->bigInteger('user_id');
                $table->string('action', 100);
                $table->text('details')->nullable();
                $table->datetime('created_at');
                $table->index(['context_id'], 'er_audit_logs_context_id');
            });
        }
    }

    /**
     * Reverse the migration on clean plugin removal.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('editorial_reminder_audit_logs');
        Capsule::schema()->dropIfExists('editorial_reminder_round_history');
        Capsule::schema()->dropIfExists('editorial_reminder_holidays');
        Capsule::schema()->dropIfExists('editorial_reminder_issue_targets');
        Capsule::schema()->dropIfExists('editorial_reminder_target_issue_logs');
        Capsule::schema()->dropIfExists('editorial_reminder_target_issues');
    }
}
