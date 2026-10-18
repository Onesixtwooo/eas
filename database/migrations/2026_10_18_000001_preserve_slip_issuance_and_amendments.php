<?php

use App\Models\ExcuseRequest;
use App\Models\SlipIssuance;
use App\Services\RequestWorkflowService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slip_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excuse_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::create('slip_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excuse_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slip_remark')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // Earlier slip details were not versioned. Freeze the best available
        // values now so future profile or catalog edits cannot change them again.
        $snapshots = app(RequestWorkflowService::class);
        ExcuseRequest::query()
            ->where(fn ($query) => $query->whereNotNull('approved_at')
                ->orWhereIn('status', ['approved', 'acknowledged', 'completed']))
            ->chunkById(100, function ($requests) use ($snapshots) {
                foreach ($requests as $request) {
                    SlipIssuance::create([
                        'excuse_request_id' => $request->id,
                        'snapshot' => $snapshots->slipSnapshot($request, 'reconstructed'),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('slip_amendments');
        Schema::dropIfExists('slip_issuances');
    }
};
