<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiTembeltriplek extends Model
{
    protected $table = 'produksi_tembel_triplek';

    protected $fillable = [
        'tanggal',
        'kendala',
    ];

    public function pegawaiTembeltripleks()
    {
        return $this->hasMany(PegawaiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function detailTembeltripleks()
    {
        return $this->hasMany(HasilTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function validasiTembeltripleks()
    {
        return $this->hasMany(ValidasiTembelTriplek::class, 'id_produksi_tembel_triplek');
    }

    public function bahanPenolongTembeltripleks()
    {
        return $this->hasMany(BahanPenolongTembeltriplek::class, 'id_produksi_tembel_triplek');
    }
}
