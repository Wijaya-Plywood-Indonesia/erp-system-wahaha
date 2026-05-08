<x-filament::widget>
    <x-filament::card class="w-full space-y-8 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= STAT UTAMA ================= --}}
        <div class="space-y-3 text-center py-4">

            {{-- TOTAL PRODUKSI --}}
            <div>
                <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Produksi (Lembar)
                </div>
            </div>

            {{-- TOTAL KUBIKASI (TAMBAHAN BARU) --}}
            <div style="margin-top: 1.5rem;">
                <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-500">
                    {{ number_format($summary['totalKubikasi'] ?? 0, 4) }} m³
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Kubikasi (m³)
                </div>
            </div>

            {{-- TOTAL PEGAWAI --}}
            <div style="margin-top: 1.5rem;">
                <div class="text-2xl font-bold text-success-600 dark:text-success-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                    Total Pegawai pada Produksi Ini (Orang)
                </div>
            </div>

        </div>

        {{-- ================= GLOBAL UKURAN + KW ================= --}}
        <div class="space-y-4">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Global Ukuran + KW
    </div>

    <div class="grid grid-cols-1 gap-3">
        @foreach ($summary['globalUkuranKw'] as $row)
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $row->ukuran }}
                <span class="text-xs text-gray-500 dark:text-gray-400">• KW {{ $row->kw }}</span>
                {{-- TAMBAHAN: Jenis Kayu --}}
                <span class="ml-1 text-xs font-semibold text-blue-500 dark:text-blue-400">
                    • {{ $row->jenis_kayu }}
                </span>
            </div>
            <div class="text-lg font-bold text-gray-900 dark:text-white">
                {{ number_format($row->total) }}
            </div>
        </div>
        @endforeach
    </div>
</div>

        {{-- ================= GLOBAL UKURAN ================= --}}
        <div class="space-y-4">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Global Ukuran (Semua KW)
    </div>

    <div class="grid grid-cols-1 gap-3">
        @foreach ($summary['globalUkuran'] as $row)
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $row->ukuran }}
                {{-- TAMBAHAN: Jenis Kayu --}}
                <span class="ml-1 text-xs font-semibold text-blue-500 dark:text-blue-400">
                    • {{ $row->jenis_kayu }}
                </span>
            </div>
            <div class="text-lg font-bold text-primary-600 dark:text-primary-400">
                {{ number_format($row->total) }}
            </div>
        </div>
        @endforeach
    </div>
</div>

        {{-- ================= RINGKASAN JENIS KAYU & UKURAN ================= --}}
        @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
        <div class="space-y-4 mt-6">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Ringkasan Penggunaan Kayu & Ukuran Hasil
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Jenis Kayu</th>
                            <th class="px-4 py-3 font-semibold">Ukuran Veneer</th>
                            <th class="px-4 py-3 font-semibold">kw</th>
                            <th class="px-4 py-3 font-semibold text-right">Hasil (Lembar)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php $grandTotal = 0; @endphp
                        @foreach (($summary['globalJenisKayuUkuran'] ?? []) as $row)
                            @php $grandTotal += $row->total; @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-4 py-3">{{ $row->jenis_kayu }}</td>
                                <td class="px-4 py-3">{{ $row->ukuran }}</td>
                                <td class="px-4 py-3">{{ $row->kw }}</td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format($row->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white font-bold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right border-t dark:border-gray-700">Total Keseluruhan</td>
                            <td class="px-4 py-3 text-right border-t dark:border-gray-700">{{ number_format($grandTotal) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

    </x-filament::card>
</x-filament::widget>