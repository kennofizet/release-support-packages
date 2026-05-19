<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('release-support.report_status_logs_table', 'release_support_report_status_logs');
        if (Schema::hasTable($table)) {
            return;
        }
        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('report_id')->index();
            $blueprint->unsignedBigInteger('user_id')->nullable()->index();
            $blueprint->string('from_status', 50)->nullable();
            $blueprint->string('to_status', 50);
            $blueprint->timestamps();
            $blueprint->softDeletes();
            $blueprint->index(['report_id', 'id'], 'rs_status_log_report_id_idx');
        });
    }

    public function down(): void
    {
        $table = config('release-support.report_status_logs_table', 'release_support_report_status_logs');
        Schema::dropIfExists($table);
    }
};
