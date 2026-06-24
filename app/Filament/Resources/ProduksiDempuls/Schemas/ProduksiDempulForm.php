<?php

namespace App\Filament\Resources\ProduksiDempuls\Schemas;

use App\Models\ProduksiDempul;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Log;

class ProduksiDempulForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal Produksi')
                    ->default(fn() => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()
                    ->rules([
                        function ($record) {
                            return function (string $attribute, $value, $fail) use ($record) {
                                $formattedDate = date('Y-m-d', strtotime($value));
                                $query = ProduksiDempul::whereDate('tanggal', $formattedDate);

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                $exists = $query->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ])
            ]);
    }
}
