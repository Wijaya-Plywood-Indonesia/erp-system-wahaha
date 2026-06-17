<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-center gap-4">
            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                Pilih Tanggal Laporan Tembel Triplek
            </label>
            <input
                type="date"
                wire:model.live="tanggal"
                max="{{ now()->format('Y-m-d') }}"
                class="block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm
                       text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-800
                       dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
            @if ($isLoading)
            <span class="text-xs text-gray-400 animate-pulse">⏳ Memuat data...</span>
            @endif
        </div>
    </x-filament::section>

    {{-- Grid Tabel Laporan Tembel Triplek --}}
    <div class="fi-ta-ctn border border-gray-200 shadow-sm rounded-xl overflow-hidden dark:border-white/10">
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3.5 border-b">Tanggal</th>
                        <th class="px-6 py-3.5 border-b">Nama Barang / Ukuran</th>
                        <th class="px-6 py-3.5 border-b text-center">No. Palet</th>
                        <th class="px-6 py-3.5 border-b text-center">Modal (Pcs)</th>
                        <th class="px-6 py-3.5 border-b text-center">Hasil (Pcs)</th>
                        <th class="px-6 py-3.5 border-b text-center">Selisih (Pcs)</th>
                        <th class="px-6 py-3.5 border-b text-center">Jumlah Pekerja</th>
                        <th class="px-6 py-3.5 border-b">Kendala</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse ($laporan as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        {{-- Tanggal --}}
                        <td class="px-6 py-4 whitespace-nowrap">{{ $item['tanggal'] }}</td>

                        {{-- Nama Barang / Ukuran --}}
                        <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">{{ $item['nama_barang'] }}</td>

                        {{-- Nomor Palet --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-mono font-bold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 rounded border border-gray-200 dark:border-gray-600">
                                {{ $item['nomor_palet'] }}
                            </span>
                        </td>

                        {{-- Total Modal (Pcs) --}}
                        <td class="px-6 py-4 text-center font-medium tabular-nums">{{ number_format($item['total_modal']) }}</td>

                        {{-- Total Hasil (Pcs) --}}
                        <td class="px-6 py-4 text-center font-extrabold text-amber-600 dark:text-amber-500 tabular-nums">
                            {{ number_format($item['total_hasil']) }}
                        </td>

                        {{-- Selisih Performa Tambal --}}
                        <td class="px-6 py-4 text-center tabular-nums">
                            @if ($item['selisih'] > 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                +{{ number_format($item['selisih']) }}
                            </span>
                            @elseif ($item['selisih'] < 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                {{ number_format($item['selisih']) }}
                                </span>
                                @else
                                <span class="text-gray-400 dark:text-gray-500 font-bold">0</span>
                                @endif
                        </td>

                        {{-- Jumlah Pekerja --}}
                        <td class="px-6 py-4 text-center font-medium whitespace-nowrap">{{ $item['ttl_pkj'] }} Orang</td>

                        {{-- Kendala Lapangan --}}
                        <td class="px-6 py-4 max-w-xs truncate" title="{{ $item['kendala'] }}">
                            <span class="text-xs text-gray-600 dark:text-gray-450 italic">{{ $item['kendala'] }}</span>
                        </td>
                    </tr>
                    @empty
                    {{-- State Kosong --}}
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <x-filament::icon icon="heroicon-o-circle-stack" class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-1" />
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tidak Ada Data Tembel Triplek</span>
                                <span class="text-xs text-gray-400">Silakan pilih tanggal lain atau pastikan data produksi harian sudah diinput.</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>