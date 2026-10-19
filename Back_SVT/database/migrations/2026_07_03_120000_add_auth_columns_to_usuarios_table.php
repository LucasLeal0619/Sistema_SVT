<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('nome', 100)->after('id');
            $table->string('email', 100)->unique()->after('nome');
            $table->string('senha')->after('email');
            $table->string('cpf', 14)->nullable()->unique()->after('senha');
            $table->string('perfil', 40)->after('cpf');
            $table->boolean('status')->default(true)->after('perfil');
            $table->string('unidade', 100)->nullable()->after('status');
            $table->string('area', 100)->nullable()->after('unidade');
            $table->string('telefone', 20)->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn([
                'nome',
                'email',
                'senha',
                'cpf',
                'perfil',
                'status',
                'unidade',
                'area',
                'telefone',
            ]);
        });
    }
};
