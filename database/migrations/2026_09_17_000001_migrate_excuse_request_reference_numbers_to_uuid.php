<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->string('legacy_reference_number')->nullable()->after('reference_number')->index();
        });

        $requests = DB::table('excuse_requests')
            ->whereNotNull('reference_number')
            ->get(['id', 'reference_number']);

        foreach ($requests as $request) {
            if (! Str::isUuid($request->reference_number)) {
                $newUuid = (string) Str::uuid();
                DB::table('excuse_requests')
                    ->where('id', $request->id)
                    ->update([
                        'legacy_reference_number' => $request->reference_number,
                        'reference_number' => $newUuid,
                    ]);
            }
        }
    }

    public function down(): void
    {
        $requests = DB::table('excuse_requests')
            ->whereNotNull('legacy_reference_number')
            ->get(['id', 'legacy_reference_number']);

        foreach ($requests as $request) {
            DB::table('excuse_requests')
                ->where('id', $request->id)
                ->update([
                    'reference_number' => $request->legacy_reference_number,
                ]);
        }

        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropIndex(['legacy_reference_number']);
            $table->dropColumn('legacy_reference_number');
        });
    }
};
