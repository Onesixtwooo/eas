<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excuse_request_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excuse_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('facilitator_id')->constrained('faculty');
            $table->timestamps();
            $table->unique(['excuse_request_id', 'subject_id']);
        });

        DB::table('excuse_requests')->orderBy('id')->chunkById(500, function ($requests) {
            DB::table('excuse_request_subject')->insert($requests->map(fn ($request) => [
                'excuse_request_id' => $request->id,
                'subject_id' => $request->subject_id,
                'facilitator_id' => $request->facilitator_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excuse_request_subject');
    }
};
