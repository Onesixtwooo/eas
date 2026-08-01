<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('faculty', function (Blueprint $table) {
            $table->string('name')->nullable()->after('employee_number');
            $table->foreignId('user_id')->nullable()->change();
        });

        foreach (DB::table('faculty')->whereNotNull('user_id')->get(['id', 'user_id']) as $instructor) {
            DB::table('faculty')->where('id', $instructor->id)->update([
                'name' => DB::table('users')->where('id', $instructor->user_id)->value('name'),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('faculty')->whereNull('user_id')->delete();

        Schema::table('faculty', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }
};
