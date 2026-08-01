<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('handwriting_samples', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $this->backfillProjectsForExistingSamples();
    }

    /**
     * MGA pivot (2026-08-01): every HandwritingSample now belongs to a
     * wrapping Project. Backfill rule agreed with the user: 1 old sample =
     * 1 new project, source inferred from the sample's creator role.
     */
    private function backfillProjectsForExistingSamples(): void
    {
        $samples = DB::table('handwriting_samples')
            ->select('id', 'user_id', 'created_by')
            ->whereNull('project_id')
            ->get();

        foreach ($samples as $sample) {
            $creatorId = $sample->created_by ?? $sample->user_id;
            $creatorRole = DB::table('users')->where('id', $creatorId)->value('role');

            $projectId = DB::table('projects')->insertGetId([
                'source' => $creatorRole === 'grafolog' ? 'grafolog' : 'client',
                'created_by' => $creatorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('handwriting_samples')->where('id', $sample->id)->update(['project_id' => $projectId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('handwriting_samples', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
