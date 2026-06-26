<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Schemas;

use App\Models\ReferensiHargaProduksi;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class ReferensiHargaProduksiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama')
                    ->maxLength(255)
                    ->placeholder('Masukkan nama referensi (opsional)'),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->relationship('ukuran', 'panjang')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->panjang}mm x {$record->lebar}mm x {$record->tebal}mm")
                    ->searchable([
                        'panjang',
                        'lebar',
                        'tebal',
                    ])
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Ukuran'),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->kode_kayu} - {$record->nama_kayu}")
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Jenis Kayu'),

                Select::make('id_sub_anak_akun')
                    ->label('Sub Anak Akun')
                    ->relationship('subAnakAkun', 'nama_sub_anak_akun')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->kode_sub_anak_akun} - {$record->nama_sub_anak_akun}")
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder('Pilih Sub Anak Akun'),

                TextInput::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->datalist(
                        collect([
                            'Afalan',
                            'Veneer Basah',
                            'Veneer Kering',
                            'Veneer Jadi',
                            'Platform',
                            'Lain-Lain',
                        ])
                            ->merge(
                                ReferensiHargaProduksi::query()
                                    ->whereNotNull('jenis_barang')
                                    ->where('jenis_barang', '!=', '')
                                    ->distinct()
                                    ->pluck('jenis_barang')
                            )
                            ->unique()
                            ->values()
                            ->toArray()
                    )
                    ->maxLength(100)
                    ->placeholder('Pilih atau ketik jenis barang baru'),

                TextInput::make('kw')
                    ->label('KW')
                    ->datalist(
                        ReferensiHargaProduksi::query()
                            ->whereNotNull('kw')
                            ->where('kw', '!=', '')
                            ->distinct()
                            ->pluck('kw')
                            ->toArray()
                    )
                    ->maxLength(50)
                    ->placeholder('Pilih atau ketik KW baru'),

                TextInput::make('harga')
                    ->label('Harga Produksi')
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 0, ',', '.') : null)
                    ->dehydrateStateUsing(fn($state) => blank($state) ? null : str_replace('.', '', $state))
                    ->placeholder('0'),
            ]);
    }
}
