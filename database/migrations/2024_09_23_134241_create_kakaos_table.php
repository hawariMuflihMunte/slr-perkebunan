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
        Schema::create('kakaos', function (Blueprint $table) {
            $table->id();
            $table->string('lokasi')->comment('Nama lokasi produksi kakao');
            $table->integer('tahun')->comment('Tahun produksi kakao');
            $table->string('periode')->comment('Periode produksi (bulan atau format lainnya)');
            $table->double('produksi')->comment('Jumlah produksi dalam ton');
            $table->float('alpha')->nullable()->comment('Nilai alpha untuk peramalan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kakaos');
    }
};
