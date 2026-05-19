<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');
        if (!Schema::hasTable($table) || Schema::hasColumn($table, 'resolved_at')) {
            return;
        }
        Schema::table($table, static function (Blueprint $blueprint): void {
            $blueprint->timestamp('resolved_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'resolved_at')) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->dropColumn('resolved_at');
            });
        }
    }
};
