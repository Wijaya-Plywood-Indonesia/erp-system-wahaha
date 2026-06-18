<x-filament-panels::page>
    <x-filament::section :collapsible="false">
        {{ $this->form }}
    </x-filament::section>

    <div class="p-0">
        <div class="w-full overflow-x-auto">
            <div class="min-w-[700px]">
                <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                    <thead>
                        <tr class="bg-zinc-700 text-white text-xs uppercase tracking-wider">
                            <th class="p-3 text-center border-r border-zinc-600 w-16">Kodep</th>
                            <th class="p-3 text-left border-r border-zinc-600">Nama Pegawai</th>
                            <th class="p-3 text-center border-r border-zinc-600 w-20">Masuk</th>
                            <th class="p-3 text-center border-r border-zinc-600 w-20">Pulang</th>
                            <th class="p-3 text-left border-r border-zinc-600">Hasil / Barang</th>
                            <th class="p-3 text-center border-r border-zinc-600 w-24">Modal (Pcs)</th>
                            <th class="p-3 text-center border-r border-zinc-600 w-24">Hasil (Pcs)</th>
                            <th class="p-3 text-center border-r border-zinc-600 w-24">Selisih (Pcs)</th>
                            <th class="p-3 text-left">Kendala</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($laporan as $index => $row)
                        <tr class="{{
                                $index % 2 === 0
                                    ? 'bg-white dark:bg-zinc-900'
                                    : 'bg-zinc-50 dark:bg-zinc-800/50'
                            }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition duration-75">

                            {{-- Kodep --}}
                            <td class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                                {{ $row['kodep'] }}
                            </td>

                            {{-- Nama Pegawai --}}
                            <td class="p-2 text-left text-xs font-semibold border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100">
                                {{ $row['nama'] }}
                            </td>

                            <!-- Jam Masuk dan Jam Pulang -->
                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">
                                {{ $row['jam_masuk'] }}
                            </td>
                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">
                                {{ $row['jam_pulang'] }}
                            </td>

                            {{-- Hasil / Nama Barang --}}
                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700">
                                @if($row['hasil'] !== '-')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300 ring-1 ring-amber-500/30">
                                    {{ $row['hasil'] }}
                                </span>
                                @else
                                <span class="text-zinc-400 italic">-</span>
                                @endif
                            </td>

                            {{-- Modal --}}
                            <td class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                {{ number_format($row['modal']) }}
                            </td>

                            {{-- Hasil (Pcs) --}}
                            <td class="p-2 text-center text-xs font-bold border-r border-zinc-300 dark:border-zinc-700 text-amber-600 dark:text-amber-400">
                                {{ number_format($row['total']) }}
                            </td>

                            {{-- Selisih --}}
                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                @if($row['selisih'] > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                    +{{ number_format($row['selisih']) }}
                                </span>
                                @elseif($row['selisih'] < 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400">
                                    {{ number_format($row['selisih']) }}
                                    </span>
                                    @else
                                    <span class="text-zinc-400 dark:text-zinc-500 font-bold">0</span>
                                    @endif
                            </td>

                            {{-- Kendala --}}
                            <td class="p-2 text-left text-xs italic text-zinc-600 dark:text-zinc-400">
                                {{ $row['kendala'] }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center">
                                    <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mb-2 opacity-50" />
                                    <p class="text-sm font-semibold">Tidak ada data pegawai untuk tanggal ini.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>

                    @if(!empty($laporan))
                    <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                        <tr>
                            <td colspan="9" class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-4">
                                <span class="font-medium">Total Pegawai:</span>
                                <strong class="text-zinc-900 dark:text-white text-sm">{{ count($laporan) }}</strong>

                                <span class="text-zinc-300">|</span>

                                <span class="font-medium">Total Modal:</span>
                                <strong class="text-zinc-700 dark:text-zinc-300 text-sm font-mono">
                                    {{ number_format(array_sum(array_column($laporan, 'modal'))) }} Pcs
                                </strong>

                                <span class="text-zinc-300">|</span>

                                <span class="font-medium">Total Hasil:</span>
                                <strong class="text-amber-600 dark:text-amber-400 text-sm font-mono">
                                    {{ number_format(array_sum(array_column($laporan, 'total'))) }} Pcs
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>