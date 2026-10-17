<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->string('slip_issuer_name')->nullable()->after('slip_remark');
        });

        // The original issuer of older slips was not recorded. Freeze the best
        // available current setting so later changes do not alter them again.
        $issuer = trim((string) (DB::table('system_settings')->where('key', 'program_head_name')->value('value')
            ?: 'PRINCESS LEA ANN D. CALINA, MSIT'));
        DB::table('excuse_requests')
            ->where(fn ($query) => $query->whereNotNull('approved_at')
                ->orWhereIn('status', ['approved', 'acknowledged', 'completed']))
            ->update(['slip_issuer_name' => $issuer]);
    }

    public function down(): void
    {
        Schema::table('excuse_requests', function (Blueprint $table) {
            $table->dropColumn('slip_issuer_name');
        });
    }
};
