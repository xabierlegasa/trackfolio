<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_requests', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('call_type', 64);
            $table->string('method', 8)->default('GET');
            $table->text('url');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('provider');
            $table->index('call_type');
            $table->index('http_status');
            $table->index('success');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_requests');
    }
};
