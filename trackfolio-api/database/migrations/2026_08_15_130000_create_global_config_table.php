<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_config', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('value');
            $table->enum('value_type', ['string', 'integer', 'bool']);
            $table->timestamps();
        });

        DB::table('global_config')->insert([
            'code' => 'is_recalculate_evolution_feature_enabled',
            'value' => '1',
            'value_type' => 'bool',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('global_config');
    }
};
