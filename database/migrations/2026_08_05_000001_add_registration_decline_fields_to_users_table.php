<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('registration_declined_at')->nullable()->after('registration_verified_at');
            $table->text('registration_decline_reason')->nullable()->after('registration_declined_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_declined_at', 'registration_decline_reason']);
        });
    }
};
