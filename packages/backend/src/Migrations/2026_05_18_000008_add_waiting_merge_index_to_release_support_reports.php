<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');

        if (!Schema::hasTable($table) || Schema::hasIndex($table, 'rs_reports_waiting_merge_idx')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->index(
                ['status', 'version_update_id', 'resolved_at', 'id'],
                'rs_reports_waiting_merge_idx',
            );
        });
    }

    public function down(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');

        if (!Schema::hasTable($table) || !Schema::hasIndex($table, 'rs_reports_waiting_merge_idx')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropIndex('rs_reports_waiting_merge_idx');
        });
    }
};
