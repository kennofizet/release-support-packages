<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('release-support.report_status_logs_table', 'release_support_report_status_logs');
        if (!Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint): void {
            $blueprint->softDeletes();
        });
    }

    public function down(): void
    {
        $table = config('release-support.report_status_logs_table', 'release_support_report_status_logs');
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'deleted_at')) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint): void {
            $blueprint->dropSoftDeletes();
        });
    }
};
