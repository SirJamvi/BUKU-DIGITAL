{{-- Shared ledger table for CustomerDepositLog (Admin Deposit Center & Customer Detail) --}}
<x-table>
    @slot('thead')
        <tr>
            <th style="width: 85px;">ID Log</th>
            <th>Waktu</th>
            @if(!empty($showCustomerColumn))
                <th>Pelanggan</th>
            @endif
            <th>Cabang</th>
            <th>Jenis &amp; Metode</th>
            <th class="text-end">Nominal &amp; Saldo</th>
            <th>Keterangan &amp; Jejak Audit</th>
            <th class="text-center" style="width: 175px;">Aksi Admin</th>
        </tr>
    @endslot

    @forelse($logs as $log)
        @php
            $isReversed = $log->isReversed();
            $isOpening = ($log->payment_method === 'opening_balance');
            $cashierOrigin = $log->cashFlow?->createdBy?->name ?? ($log->createdBy?->name ?? 'Sistem');
            $affectsDrawer = $log->cashFlow ? (bool) $log->cashFlow->affects_drawer : false;

            if ($log->type === 'credit') {
                $typeLabel = $isOpening ? 'SALDO AWAL' : 'SETORAN DEPOSIT';
                $typeBadge = $isReversed
                    ? 'bg-secondary text-white'
                    : ($isOpening
                        ? 'bg-info bg-opacity-10 text-info border border-info border-opacity-25'
                        : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25');
            } elseif ($log->type === 'debit') {
                $typeLabel = 'TERPAKAI';
                $typeBadge = 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
            } elseif ($log->type === 'void_restore') {
                $typeLabel = 'PULIH VOID';
                $typeBadge = 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50';
            } else {
                $typeLabel = 'VOID / PEMBALIK';
                $typeBadge = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
            }

            $isNegativeEffect = in_array($log->type, ['debit', 'refund'], true);
            $methodText = $isOpening ? 'Saldo Awal (Tanpa Laci)' : strtoupper(str_replace('-', ' ', $log->payment_method ?? 'cash'));
        @endphp
        <tr class="{{ $isReversed ? 'table-secondary opacity-75' : '' }}">
            <td>
                <span class="badge bg-dark">#{{ $log->id }}</span>
                @if($isReversed)
                    <br><span class="badge bg-danger mt-1" style="font-size: 0.68rem;">DIBATALKAN</span>
                @endif
            </td>
            <td class="small">
                <div class="fw-semibold text-dark">{{ $log->created_at?->format('d/m/Y') }}</div>
                <div class="text-muted">{{ $log->created_at?->format('H:i') }} WIB</div>
            </td>
            @if(!empty($showCustomerColumn))
                <td>
                    @if($log->customer)
                        <a href="{{ route('admin.customers.show', $log->customer_id) }}" class="fw-bold text-decoration-none text-dark">
                            {{ $log->customer->name }}
                        </a>
                        @if($log->customer->phone)
                            <div class="small text-muted">{{ $log->customer->phone }}</div>
                        @endif
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
            @endif
            <td class="small">
                <span class="badge bg-light text-dark border">{{ $log->branch->name ?? 'Semua Cabang' }}</span>
                <div class="text-muted mt-1" style="font-size: 0.72rem;">
                    Input: {{ $log->createdBy->name ?? 'Sistem' }}
                </div>
            </td>
            <td>
                <span class="badge {{ $typeBadge }}">{{ $typeLabel }}</span>
                <div class="small fw-semibold text-dark mt-1">{{ $methodText }}</div>
                @if($log->cashFlow)
                    <div class="small text-muted" style="font-size: 0.72rem;">
                        @if($affectsDrawer)
                            <i class="fas fa-cash-register text-success me-1"></i>Laci: {{ $cashierOrigin }}
                        @else
                            <i class="fas fa-building-columns text-info me-1"></i>Non-Laci / Owner
                        @endif
                    </div>
                @endif
            </td>
            <td class="text-end">
                <div class="fw-bold {{ $isReversed ? 'text-decoration-line-through text-muted' : ($isNegativeEffect ? 'text-danger' : 'text-success') }}">
                    {{ $isNegativeEffect ? '-' : '+' }}Rp {{ number_format((float) $log->amount, 0, ',', '.') }}
                </div>
                <div class="small text-muted" style="font-size: 0.72rem;">
                    Saldo: Rp {{ number_format((float) $log->balance_before, 0, ',', '.') }} &rarr; <strong>Rp {{ number_format((float) $log->balance_after, 0, ',', '.') }}</strong>
                </div>
            </td>
            <td class="small" style="max-width: 280px;">
                <div class="text-dark">{{ $log->notes ?: '-' }}</div>
                @if($log->transaction_id)
                    <div class="mt-1">
                        <a href="{{ route('admin.transactions.show', $log->transaction_id) }}" class="badge bg-light text-primary border text-decoration-none">
                            <i class="fas fa-receipt me-1"></i>Transaksi #{{ str_pad((string) $log->transaction_id, 6, '0', STR_PAD_LEFT) }}
                        </a>
                    </div>
                @endif
                @if($isReversed)
                    <div class="mt-1 p-1 rounded bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.74rem;">
                        <strong>Void oleh {{ $log->reversedBy->name ?? 'Admin' }}</strong>
                        ({{ $log->reversed_at?->format('d/m H:i') }}):
                        {{ $log->reversal_reason }}
                        @if($log->reversed_by_log_id)
                            <span class="ms-1 fw-bold">[Ref #{{ $log->reversed_by_log_id }}]</span>
                        @endif
                    </div>
                @elseif($log->reverses_log_id)
                    <div class="mt-1 text-danger fw-semibold" style="font-size: 0.74rem;">
                        <i class="fas fa-rotate-left me-1"></i>Membalikkan Log #{{ $log->reverses_log_id }}
                    </div>
                @endif
            </td>
            <td class="text-center">
                @if($log->isReversibleCredit())
                    <div class="d-flex justify-content-center gap-1 flex-wrap">
                        <button type="button"
                                class="btn btn-sm btn-outline-warning text-dark fw-semibold btn-correct-deposit"
                                data-bs-toggle="modal"
                                data-bs-target="#modalCorrectDeposit"
                                data-correct-url="{{ route('admin.deposits.correct', $log->id) }}"
                                data-log-id="{{ $log->id }}"
                                data-customer-name="{{ $log->customer->name ?? 'Pelanggan' }}"
                                data-current-balance="{{ number_format((float) ($log->customer->deposit_balance ?? 0), 0, ',', '.') }}"
                                data-amount="{{ (float) $log->amount }}"
                                data-amount-formatted="Rp {{ number_format((float) $log->amount, 0, ',', '.') }}"
                                data-method="{{ strtolower($log->payment_method ?? 'cash') }}"
                                data-cashflow-info="{{ $log->cashFlow ? ($affectsDrawer ? 'Laci Kasir (' . $cashierOrigin . ')' : 'Non-Laci / Rekening Owner') : 'Saldo Awal (Tanpa Cash Flow)' }}">
                            <i class="fas fa-pen-to-square me-1"></i>Koreksi
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger fw-semibold btn-reverse-deposit"
                                data-bs-toggle="modal"
                                data-bs-target="#modalReverseDeposit"
                                data-reverse-url="{{ route('admin.deposits.reverse', $log->id) }}"
                                data-log-id="{{ $log->id }}"
                                data-customer-name="{{ $log->customer->name ?? 'Pelanggan' }}"
                                data-current-balance="{{ number_format((float) ($log->customer->deposit_balance ?? 0), 0, ',', '.') }}"
                                data-amount-formatted="Rp {{ number_format((float) $log->amount, 0, ',', '.') }}"
                                data-cashflow-info="{{ $log->cashFlow ? ($affectsDrawer ? 'Contra-Entry memotong Laci Kasir (' . $cashierOrigin . ')' : 'Contra-Entry Non-Laci / Rekening Owner') : 'Tanpa Cash Flow (Hanya kurangi saldo deposit)' }}">
                            <i class="fas fa-ban me-1"></i>Void
                        </button>
                    </div>
                @elseif($isReversed)
                    <span class="badge bg-light text-muted border">Sudah Dibatalkan</span>
                @else
                    <span class="text-muted small">-</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ !empty($showCustomerColumn) ? 8 : 7 }}" class="text-center text-muted py-4">
                Belum ada riwayat mutasi deposit pada filter yang dipilih.
            </td>
        </tr>
    @endforelse
</x-table>
