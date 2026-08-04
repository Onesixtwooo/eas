<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->timestamp('edited_at')->nullable()->after('read_at');
            $table->timestamp('unsent_at')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'unsent_at']);
            $table->text('body')->nullable(false)->change();
        });
    }
};
