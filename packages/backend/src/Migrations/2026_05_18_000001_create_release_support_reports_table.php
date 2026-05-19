<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('user_id')->index();
            $blueprint->string('title', 255);
            $blueprint->text('description')->nullable();
            $blueprint->string('status', 50)->default('open')->index();
            $blueprint->string('app_version', 120)->nullable()->index();
            $blueprint->json('captured_logs')->nullable();
            $blueprint->json('captured_context')->nullable();
            $blueprint->json('drawings')->nullable();
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();
            $blueprint->index(['user_id', 'status', 'id'], 'rs_reports_user_status_id_idx');
        });
    }

    public function down(): void
    {
        $table = config('release-support.reports_table', 'release_support_reports');
        Schema::dropIfExists($table);
    }
};
