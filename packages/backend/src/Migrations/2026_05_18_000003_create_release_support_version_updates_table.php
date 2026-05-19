<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = config('release-support.version_updates_table', 'release_support_version_updates');
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('version', 120)->index();
            $blueprint->string('title', 255)->nullable();
            $blueprint->text('content')->nullable();
            $blueprint->boolean('is_force')->default(false)->index();
            $blueprint->boolean('is_active')->default(true)->index();
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();
            $blueprint->index(['is_active', 'id'], 'rs_updates_active_id_idx');
        });
    }

    public function down(): void
    {
        $table = config('release-support.version_updates_table', 'release_support_version_updates');
        Schema::dropIfExists($table);
    }
};
