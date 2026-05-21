<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');
        $versionsTable = config('release-support.version_updates_table', 'release_support_version_updates');

        Schema::table($table, function (Blueprint $blueprint) use ($versionsTable) {
            $blueprint->unsignedBigInteger('version_update_id')->nullable()->after('resolved_at');
            $blueprint->timestamp('merged_at')->nullable()->after('version_update_id');
            $blueprint->index('version_update_id');
            $blueprint->foreign('version_update_id')
                ->references('id')
                ->on($versionsTable)
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropForeign(['version_update_id']);
            $blueprint->dropIndex(['version_update_id']);
            $blueprint->dropColumn(['version_update_id', 'merged_at']);
        });
    }
};
