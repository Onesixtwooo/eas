<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('excuse_request_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('archive_id')->unique();
            $table->unsignedBigInteger('original_request_id')->index();
            $table->string('reference_number')->nullable()->index();
            $table->string('legacy_reference_number')->nullable()->index();
            $table->string('status');
            $table->unsignedBigInteger('archived_by_id')->nullable();
            $table->string('archived_by_name');
            $table->longText('records');
            $table->longText('files');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excuse_request_archives');
    }
};
