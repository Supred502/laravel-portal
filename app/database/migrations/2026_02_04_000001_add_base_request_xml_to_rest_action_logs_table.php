<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rest_action_logs', function (Blueprint $table) {
            $table->longText('base_request_xml')->nullable()->after('request_xml');
        });
    }

    public function down(): void
    {
        Schema::table('rest_action_logs', function (Blueprint $table) {
            $table->dropColumn('base_request_xml');
        });
    }
};
