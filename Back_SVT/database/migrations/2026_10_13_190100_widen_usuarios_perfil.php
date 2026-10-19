<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuarios') || ! Schema::hasColumn('usuarios', 'perfil')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE usuarios MODIFY perfil VARCHAR(40) NOT NULL');
        }
    }

    public function down(): void
    {
        // Os perfis do SVT não cabem no enum antigo.
    }
};
