<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kopis', function (Blueprint $table) {
            $table->float('alpha')->nullable(); // Tambahkan kolom alpha dengan tipe data float
        });
    }
    
    public function down()
    {
        Schema::table('kopis', function (Blueprint $table) {
            $table->dropColumn('alpha'); // Menghapus kolom alpha jika terjadi rollback
        });
    }
};
