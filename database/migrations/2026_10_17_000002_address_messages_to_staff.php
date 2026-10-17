<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'recipient_id')) {
            Schema::table('messages', function (Blueprint $table) {
                // Null identifies the former shared administrative conversation.
                $table->foreignId('recipient_id')->nullable()->after('sender_id')->constrained('users')->restrictOnDelete();
                $table->index(['student_id', 'recipient_id', 'id']);
            });
        }

        if (! Schema::hasColumn('message_conversation_states', 'partner_id')) {
            Schema::table('message_conversation_states', function (Blueprint $table) {
                $table->unsignedBigInteger('partner_id')->default(0)->after('student_id');
                $table->unsignedBigInteger('last_read_message_id')->default(0)->after('deleted_before_message_id');
                // Add this before dropping the old unique index: MySQL uses it for the user FK.
                $table->unique(['user_id', 'student_id', 'partner_id'], 'message_state_thread_unique');
            });
        }
        Schema::table('message_conversation_states', fn (Blueprint $table) => $table->dropUnique(['user_id', 'student_id']));
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('messages')->whereNotNull('recipient_id')->exists()) {
            throw new RuntimeException('Addressed messages must be retained; this migration cannot be rolled back after use.');
        }
        Schema::table('message_conversation_states', fn (Blueprint $table) => $table->unique(['user_id', 'student_id']));
        Schema::table('message_conversation_states', function (Blueprint $table) {
            $table->dropUnique('message_state_thread_unique');
            $table->dropColumn(['partner_id', 'last_read_message_id']);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'recipient_id', 'id']);
            $table->dropConstrainedForeignId('recipient_id');
        });
    }
};
