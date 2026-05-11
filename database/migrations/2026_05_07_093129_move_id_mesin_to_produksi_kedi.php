<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add id_mesin to produksi_kedi
        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->foreignId('id_mesin')
                ->nullable()
                ->after('tanggal')
                ->constrained('mesins')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        // 2. Data Migration (Optional: try to fill id_mesin from detail tables if they exist)
        // This is a bit tricky since there could be multiple details with different machines, 
        // but based on the request, it seems each production should have one machine.
        $productions = DB::table('produksi_kedi')->get();
        foreach ($productions as $prod) {
            $machineId = DB::table('detail_masuk_kedi')->where('id_produksi_kedi', $prod->id)->value('id_mesin')
                ?? DB::table('detail_bongkar_kedi')->where('id_produksi_kedi', $prod->id)->value('id_mesin')
                ?? DB::table('detail_pegawai_kedi')->where('id_produksi_kedi', $prod->id)->value('id_mesin');
            
            if ($machineId) {
                DB::table('produksi_kedi')->where('id', $prod->id)->update(['id_mesin' => $machineId]);
            }
        }

        // 3. Remove id_mesin from detail tables
        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            $table->dropForeign(['id_mesin']);
            $table->dropColumn('id_mesin');
        });

        Schema::table('detail_bongkar_kedi', function (Blueprint $table) {
            $table->dropForeign(['id_mesin']);
            $table->dropColumn('id_mesin');
        });

        Schema::table('detail_pegawai_kedi', function (Blueprint $table) {
            $table->dropForeign(['id_mesin']);
            $table->dropColumn('id_mesin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_pegawai_kedi', function (Blueprint $table) {
            $table->foreignId('id_mesin')
                ->nullable()
                ->constrained('mesins')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::table('detail_bongkar_kedi', function (Blueprint $table) {
            $table->foreignId('id_mesin')
                ->nullable()
                ->constrained('mesins')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            $table->foreignId('id_mesin')
                ->nullable()
                ->constrained('mesins')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->dropForeign(['id_mesin']);
            $table->dropColumn('id_mesin');
        });
    }
};
