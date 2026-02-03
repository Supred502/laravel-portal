<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rest_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10);
            $table->text('url');
            $table->integer('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->longText('request_xml')->nullable();
            $table->longText('response_xml')->nullable();
            $table->text('error_message')->nullable();
            $table->string('auth_username')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rest_action_logs');
    }
};
