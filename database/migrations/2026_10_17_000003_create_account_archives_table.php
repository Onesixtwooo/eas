<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('archive_id')->unique();
            $table->unsignedBigInteger('original_user_id')->index();
            $table->string('account_name');
            $table->string('account_email');
            $table->string('account_role');
            $table->unsignedBigInteger('archived_by_id')->nullable();
            $table->string('archived_by_name');
            $table->string('outcome', 20);
            $table->longText('records');
            $table->longText('files');
            $table->timestamps();
            $table->index(['account_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_archives');
    }
};
