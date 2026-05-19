<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('release-support.report_comments_table', 'release_support_report_comments');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->unsignedBigInteger('report_id')->index();
            $blueprint->unsignedBigInteger('user_id')->index();
            $blueprint->text('comment');
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();
            $blueprint->index(['report_id', 'id'], 'rs_comments_report_id_idx');
        });
    }

    public function down(): void
    {
        $table = config('release-support.report_comments_table', 'release_support_report_comments');
        Schema::dropIfExists($table);
    }
};
