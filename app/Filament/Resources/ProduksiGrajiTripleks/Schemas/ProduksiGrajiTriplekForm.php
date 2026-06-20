<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use App\Models\ProduksiGrajitriplek;
use Filament\Forms\Components\Select;

class ProduksiGrajiTriplekForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->default(fn() => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()
                    ->rules([
                        function ($get, $record) {
                            return function (string $attribute, $value, $fail) use ($get, $record) {
                                // Jika shift belum dipilih, tidak perlu validasi dulu
                                if (! $get('shift')) {
                                    return;
                                }

                                $query = ProduksiGrajitriplek::whereDate('tanggal_produksi', $value)
                                    ->where('shift', $get('shift'));

                                // ⛔ Amankan fitur: skip pengecekan saat mode EDIT
                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                if ($query->exists()) {
                                    $fail('Sesi produksi untuk tanggal dan shift ini sudah terdaftar.');
                                }
                            };
                        },
                    ]),

                Select::make('status')
                    ->label('Status Produksi')
                    ->options([
                        'graji manual'   => 'Graji Manual',
                        'graji otomatis' => 'Graji Otomatis',
                    ])
                    ->required()
                    ->validationMessages([
                        'required' => 'Status produksi wajib dipilih.',
                    ]),

                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'pagi' => 'Pagi',
                        'malam' => 'Malam',
                    ])
                    ->required()
                    ->reactive(), // 🔥 penting
            ]);
    }
}
