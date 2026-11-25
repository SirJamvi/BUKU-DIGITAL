{{-- resources/views/kasir/dashboard/index.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Dashboard Kasir')

@section('breadcrumb')
@endsection

@push('styles')
<style>
    /* CSS khusus Dashboard Kasir (.kd-*) */
    .kd-trip-card {
        border-left-width: 4px !important;
        border-left-style: solid !important;
        border-left-color: var(--kasir-primary) !important;
    }

    .kd-trip-card--warn {
        border-left-color: var(--kasir-accent) !important;
    }

    .kd-trip-card--danger {
        border-left-color: var(--kasir-danger) !important;
    }

    .kd-trip-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.78rem;
        font-weight: 700;
        background: var(--kasir-brand-soft);
        color: var(--kasir-secondary);
        border: 1px solid var(--kasir-brand-border);
        padding: 0.2rem 0.55rem;
        border-radius: 6px;
    }

    .kd-trip-list {
        background: var(--kasir-surface-subtle);
        border: 1px solid var(--kasir-border);
        border-radius: 10px;
        max-height: 185px;
        overflow-y: auto;
        padding: 0.5rem 0.75rem;
    }

    .kd-kasbon-scroll {
        max-height: 520px;
        overflow-y: auto;
    }

    .kd-kasbon-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .kd-kasbon-toolbar {
        padding: 0.85rem 1.25rem;
        background: var(--kasir-surface-subtle);
        border-bottom: 1px solid var(--kasir-border);
    }

    .kd-kasbon-actions {
        display: inline-flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.4rem;
    }

    /* CSS khusus Modal Penyelesaian Pengiriman (.kd-settle-*) */
    .kd-settle-dialog {
        max-width: 1120px;
        width: calc(100% - 2rem);
    }

    .kd-settle-content {
        display: flex;
        flex-direction: column;
        max-height: 92vh;
        border: 0;
        border-radius: var(--kasir-radius-lg);
        overflow: hidden;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.22);
    }

    .kd-settle-form {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    .kd-settle-header {
        background: var(--kasir-gradient);
        color: #fff;
        flex-shrink: 0;
        padding: 1rem 1.25rem;
        border-bottom: 0;
    }

    .kd-settle-close {
        min-width: 44px;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        opacity: 0.92;
        transition: background-color 150ms ease, opacity 150ms ease;
    }

    .kd-settle-close:hover {
        background-color: rgba(255, 255, 255, 0.16);
        opacity: 1;
    }

    .kd-settle-subheader {
        flex-shrink: 0;
        background: #ffffff;
        border-bottom: 1px solid var(--kasir-border);
        padding: 0.85rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .kd-settle-body {
        overflow-y: auto;
        flex: 1 1 auto;
        min-height: 0;
        background: var(--kasir-bg-dominant);
        padding: 1rem 1.25rem;
    }

    .kd-settle-card {
        background: var(--kasir-surface);
        border: 1px solid var(--kasir-border);
        border-radius: var(--kasir-radius-md);
        padding: 1rem 1.15rem;
        margin-bottom: 0.85rem;
        box-shadow: var(--kasir-shadow-sm);
        transition: border-color 180ms ease, box-shadow 180ms ease;
    }

    .kd-settle-card--deposit-open {
        border: 2px solid #0d9488 !important;
        box-shadow: 0 4px 14px rgba(13, 148, 136, 0.14);
    }

    .kd-settle-card--unselected {
        border: 2px solid var(--kasir-accent) !important;
        box-shadow: 0 4px 14px rgba(245, 158, 11, 0.16);
    }

    .kd-settle-row {
        display: grid;
        grid-template-columns: minmax(240px, 1.35fr) minmax(145px, 0.7fr) minmax(360px, 1.5fr);
        gap: 1rem;
        align-items: start;
    }

    .kd-settle-segments {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
        gap: 0.45rem;
    }

    .kd-settle-seg-btn {
        min-height: 48px;
        padding: 0.45rem 0.65rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.84rem;
        border: 1.5px solid var(--kasir-border);
        background: #ffffff;
        color: var(--kasir-text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        text-align: center;
        cursor: pointer;
        transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
    }

    .kd-settle-seg-btn:hover {
        border-color: var(--kasir-primary);
        background: var(--kasir-brand-soft);
    }

    .kd-settle-seg-btn.is-active {
        background: var(--kasir-primary);
        border-color: var(--kasir-primary);
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.25);
    }

    .kd-settle-seg-btn.is-kasbon {
        border-color: #ef4444;
        color: #b91c1c;
        background: #ffffff;
    }

    .kd-settle-seg-btn.is-kasbon:hover {
        background: #fef2f2;
        border-color: #dc2626;
    }

    .kd-settle-seg-btn.is-kasbon.is-active {
        background: #dc2626;
        border-color: #dc2626;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
    }

    .kd-settle-date-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.55rem;
    }

    .kd-settle-date-btn {
        min-height: 38px;
        padding: 0.35rem 0.7rem;
        border-radius: 8px;
        border: 1px solid var(--kasir-border);
        background: var(--kasir-surface-subtle);
        color: var(--kasir-text-muted);
        font-size: 0.78rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        cursor: pointer;
        text-align: left;
        white-space: normal;
        transition: all 150ms ease;
    }

    .kd-settle-date-btn.is-active {
        background: var(--kasir-brand-soft);
        border-color: var(--kasir-primary);
        color: var(--kasir-text);
    }

    .kd-settle-date-btn.is-warn.is-active {
        background: #fffbeb;
        border-color: var(--kasir-accent);
        color: #92400e;
    }

    .kd-settle-kasbon-alert {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 0.55rem 0.8rem;
        font-weight: 600;
        font-size: 0.84rem;
        margin-top: 0.65rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .kd-settle-unselected-msg {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
        font-weight: 600;
        font-size: 0.82rem;
        margin-top: 0.55rem;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .kd-settle-card-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--kasir-border);
    }

    .kd-settle-note-toggle {
        min-height: 40px;
        padding: 0.35rem 0.8rem;
        border-radius: 8px;
        border: 1px solid var(--kasir-border);
        background: #ffffff;
        color: var(--kasir-text-muted);
        font-size: 0.82rem;
        font-weight: 600;
        transition: all 150ms ease;
    }

    .kd-settle-note-toggle:hover {
        background: var(--kasir-surface-subtle);
        color: var(--kasir-text);
    }

    .kd-settle-deposit-btn {
        min-height: 40px;
        padding: 0.35rem 0.9rem;
        border-radius: 8px;
        border: 1.5px solid #0d9488;
        background: #ffffff;
        color: #0f766e;
        font-size: 0.82rem;
        font-weight: 600;
        transition: all 150ms ease;
    }

    .kd-settle-deposit-btn:hover {
        background: #f0fdfa;
        color: #0f766e;
    }

    .kd-settle-deposit-btn.is-open {
        background: #0d9488;
        border-color: #0d9488;
        color: #ffffff;
    }

    .kd-settle-deposit-panel {
        background: #f0fdfa;
        border: 1px solid #99f6e4;
        border-radius: var(--kasir-radius-md);
        padding: 1.25rem;
        margin-top: 0.85rem;
    }

    .kd-settle-dep-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.85rem;
    }

    .kd-settle-dep-box {
        background: #ffffff;
        border: 1px solid #ccfbf1;
        border-radius: 10px;
        padding: 0.85rem 1rem;
    }

    .kd-settle-dep-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--kasir-text-muted);
        margin-bottom: 0.35rem;
    }

    .kd-settle-dep-val {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .kd-settle-quick-btn {
        min-height: 44px;
        padding: 0.4rem 0.85rem;
        border-radius: 8px;
        border: 1px solid #99f6e4;
        background: #ffffff;
        color: #0f766e;
        font-size: 0.84rem;
        font-weight: 600;
        transition: all 150ms ease;
    }

    .kd-settle-quick-btn:hover {
        background: #ccfbf1;
        border-color: #0d9488;
    }

    .kd-settle-footer {
        flex-shrink: 0;
        background: #ffffff;
        border-top: 1px solid var(--kasir-border);
        padding: 0.85rem 1.25rem;
    }

    .kd-settle-summary-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem 1.35rem;
    }

    .kd-settle-sum-item {
        display: flex;
        flex-direction: column;
        line-height: 1.25;
    }

    .kd-settle-sum-label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--kasir-text-muted);
    }

    .kd-settle-sum-val {
        font-size: 1.05rem;
        font-weight: 700;
    }

    .kd-settle-submit-btn {
        background: var(--kasir-accent);
        color: #0f172a;
        border: none;
        min-height: 50px;
        padding: 0.55rem 1.35rem;
        border-radius: 10px;
        font-weight: 700;
        transition: all 150ms ease;
    }

    .kd-settle-submit-btn:hover,
    .kd-settle-submit-btn:focus {
        background: #d97706;
        color: #0f172a;
    }

    .kd-settle-seg-btn:focus-visible,
    .kd-settle-date-btn:focus-visible,
    .kd-settle-quick-btn:focus-visible,
    .kd-settle-deposit-btn:focus-visible,
    .kd-settle-dep-done-btn:focus-visible,
    .kd-settle-note-toggle:focus-visible,
    .kd-settle-close:focus-visible,
    .kd-settle-submit-btn:focus-visible {
        outline: 3px solid rgba(102, 126, 234, 0.45);
        outline-offset: 2px;
    }

    /* Input >= 16px agar iPad Safari tidak auto-zoom saat fokus */
    .kd-settle-driver-input {
        font-size: max(16px, 1.25rem) !important;
        min-height: 46px;
    }

    .kd-settle-dep-done-btn {
        min-height: 44px;
        padding: 0.4rem 0.9rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .kd-settle-result-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }

    /* ============================================================ */
    /* MODE KOMPAK SAAT KEYBOARD LAYAR MUNCUL (.kd-settle-kbd)       */
    /* ============================================================ */
    .kd-settle-modal.kd-settle-kbd .kd-settle-dialog {
        align-items: flex-start !important;
        min-height: auto !important;
        margin-top: calc(var(--kd-vvtop, 0px) + 8px) !important;
        margin-bottom: 8px !important;
        transition: margin-top 160ms ease;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-content {
        max-height: calc(var(--kd-vvh, 100vh) - 16px);
        transition: max-height 160ms ease;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-header {
        padding: 0.45rem 1rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-header-sub {
        display: none !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-subheader {
        padding: 0.4rem 1rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-subheader-hint {
        display: none !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-subheader > div:last-child {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        width: 100%;
        justify-content: space-between;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-subheader .fs-4 {
        font-size: 1.1rem !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-body {
        padding: 0.6rem 0.85rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-deposit-panel {
        padding: 0.65rem 0.8rem;
        margin-top: 0.5rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-subtitle {
        display: none !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-head {
        margin-bottom: 0.45rem !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-grid {
        display: grid;
        grid-template-columns: 1.35fr 1fr;
        gap: 0.5rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-input-box {
        order: 1;
        padding: 0.45rem 0.65rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-tagihan-box {
        order: 2;
        padding: 0.45rem 0.65rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-tagihan-box .kd-settle-dep-val {
        font-size: 1.05rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-saldo-box {
        order: 3;
        grid-column: 1 / -1;
        padding: 0.3rem 0.65rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-saldo-box .kd-settle-dep-label {
        margin-bottom: 0;
        font-size: 0.7rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-dep-saldo-box .kd-settle-dep-val {
        font-size: 0.88rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-quick-row {
        flex-wrap: nowrap !important;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-top: 0.45rem !important;
        padding-bottom: 2px;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-quick-btn {
        flex-shrink: 0;
        min-height: 44px;
        padding: 0.3rem 0.65rem;
        font-size: 0.8rem;
    }

    .kd-settle-modal.kd-settle-kbd .summary-deposit-live {
        margin-top: 0.45rem !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-result-grid {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.45rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-result-grid .kd-settle-dep-box {
        padding: 0.4rem 0.55rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-result-grid .kd-settle-dep-label {
        font-size: 0.64rem;
        margin-bottom: 0.15rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-result-grid .kd-settle-dep-val {
        font-size: 0.95rem;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-footer {
        padding: 0.45rem 1rem;
        gap: 0.35rem !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-footer-info {
        display: none !important;
    }

    .kd-settle-modal.kd-settle-kbd .kd-settle-submit-btn {
        min-height: 44px;
        padding: 0.4rem 1rem;
        font-size: 0.88rem;
    }

    @media (max-width: 1023.98px) {
        .kd-settle-row {
            grid-template-columns: 1fr auto;
        }
        .kd-settle-col-method {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 767.98px) {
        .kd-settle-row {
            grid-template-columns: 1fr;
        }
        .kd-settle-col-amount {
            text-align: left !important;
        }
        .kd-settle-segments {
            grid-template-columns: repeat(2, 1fr);
        }
        .kd-settle-dep-grid {
            grid-template-columns: 1fr;
        }
        .kd-settle-result-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 575.98px) {
        .kd-settle-dialog {
            margin: 0;
            max-width: 100%;
            width: 100%;
            height: 100%;
        }
        .kd-settle-content {
            max-height: 100vh;
            height: 100vh;
            border-radius: 0;
        }
        .kd-settle-modal.kd-settle-kbd .kd-settle-dialog {
            margin: 0 !important;
            margin-top: var(--kd-vvtop, 0px) !important;
        }
        .kd-settle-modal.kd-settle-kbd .kd-settle-content {
            height: var(--kd-vvh, 100vh);
            max-height: var(--kd-vvh, 100vh);
        }
        .kd-settle-body {
            padding: 0.75rem;
        }
    }

    /* ============================================================ */
    /* MODAL DAFTAR KASBON PER PELANGGAN (.kd-kasbon-*)             */
    /* ============================================================ */
    .kd-kasbon-dialog {
        max-width: 920px;
        width: calc(100% - 2rem);
    }

    .kd-kasbon-content {
        display: flex;
        flex-direction: column;
        max-height: 92vh;
        border: 0;
        border-radius: var(--kasir-radius-lg);
        overflow: hidden;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.22);
    }

    .kd-kasbon-form {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    .kd-kasbon-header {
        background: var(--kasir-gradient);
        color: #ffffff;
        flex-shrink: 0;
        padding: 0.9rem 1.25rem;
        border-bottom: 0;
    }

    .kd-kasbon-subheader {
        flex-shrink: 0;
        background: var(--kasir-surface-subtle);
        border-bottom: 1px solid var(--kasir-border);
        padding: 0.75rem 1.25rem;
    }

    .kd-kasbon-scroll-area {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        background: #ffffff;
    }

    .kd-kasbon-row {
        cursor: pointer;
        transition: background-color 140ms ease;
    }

    .kd-kasbon-row.is-selected > td {
        background-color: var(--kasir-brand-soft) !important;
    }

    .kd-kasbon-row.is-selected > td:first-child {
        box-shadow: inset 3px 0 0 var(--kasir-primary);
    }

    .kd-kasbon-chk-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
        min-height: 44px;
        margin: -6px 0 -6px -6px;
        cursor: pointer;
    }

    .kd-kasbon-amount-cell {
        white-space: nowrap;
    }

    .kd-kasbon-footer {
        flex-shrink: 0;
        background: #ffffff;
        border-top: 1px solid var(--kasir-border);
        padding: 0.75rem 1.25rem;
    }

    .kd-kasbon-summary-bar {
        width: 100%;
        background: var(--kasir-surface-subtle);
        border: 1px solid var(--kasir-brand-border);
        border-radius: 10px;
        padding: 0.6rem 0.95rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.4rem 1rem;
        transition: border-color 140ms ease, background-color 140ms ease;
    }

    .kd-kasbon-summary-total {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--kasir-primary);
        transition: color 140ms ease;
    }

    .kd-kasbon-submit-btn {
        min-height: 48px;
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        font-weight: 700;
        transition: all 140ms ease;
    }

    .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-dialog {
        align-items: flex-start !important;
        min-height: auto !important;
        margin-top: calc(var(--kd-vvtop, 0px) + 8px) !important;
        margin-bottom: 8px !important;
    }

    .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-content {
        max-height: calc(var(--kd-vvh, 100vh) - 16px);
    }

    .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-subheader,
    .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-footer-hint {
        display: none !important;
    }

    .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-footer {
        padding: 0.5rem 1rem;
    }

    .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-submit-btn {
        min-height: 44px;
    }

    @media (max-width: 575.98px) {
        .kd-kasbon-dialog {
            margin: 0;
            max-width: 100%;
            width: 100%;
            height: 100%;
        }
        .kd-kasbon-content {
            max-height: 100vh;
            height: 100vh;
            border-radius: 0;
        }
        .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-dialog {
            margin: 0 !important;
            margin-top: var(--kd-vvtop, 0px) !important;
        }
        .kasbon-bulk-modal.kd-kasbon-kbd .kd-kasbon-content {
            height: var(--kd-vvh, 100vh);
            max-height: var(--kd-vvh, 100vh);
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    {{-- Salam Pembuka --}}
    <div class="mb-4">
        <h3 class="fw-bold mb-1">Selamat Datang, {{ Auth::user()->name ?? 'Kasir' }}!</h3>
        <p class="text-muted mb-0">Berikut adalah ringkasan aktivitas penjualan Anda hari ini.</p>
    </div>

    {{-- ================================================================= --}}
    {{-- SECTION 1: PENGIRIMAN DRIVER BERJALAN (URUTAN ASLI TERBARU DULU)  --}}
    {{-- ================================================================= --}}
    @if(isset($activeTrips) && $activeTrips->isNotEmpty())
    <div class="kasir-card mb-4">
        <div class="kasir-section-header kasir-section-header--brand">
            <div>
                <h5 class="kasir-section-title">
                    <i class="fas fa-truck-moving" style="color: var(--kasir-primary);"></i>
                    <span>PENGIRIMAN DRIVER BERJALAN</span>
                </h5>
                <p class="kasir-section-subtitle">
                    Terdapat {{ $activeTrips->count() }} rombongan pengiriman driver sedang dalam perjalanan membawa pesanan.
                </p>
            </div>
            <div>
                <span class="kasir-badge-warning">
                    <i class="fas fa-clock"></i>
                    <span>{{ $activeTrips->flatten()->count() }} Pesanan Belum Selesai</span>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($activeTrips as $tripKey => $tripTrx)
                @php
                    $firstTrx = $tripTrx->first();
                    $driverName = $firstTrx->driver->name ?? 'Driver';
                    $driverPhone = $firstTrx->driver->phone ?? '-';
                    $tripCode = $firstTrx->delivery_trip_code ?? $tripKey;
                    $tripTotal = $tripTrx->sum('total_amount');
                    $modalTripId = 'modalTrip_' . \Illuminate\Support\Str::slug($tripKey);
                    $totalQtyInTrip = 0;
                    foreach($tripTrx as $trxItem) {
                        $totalQtyInTrip += $trxItem->details->sum('quantity');
                    }
                    $ageHours = $firstTrx->transaction_date ? (int) $firstTrx->transaction_date->diffInHours(now()) : 0;
                    $ageDays = $firstTrx->transaction_date ? (int) $firstTrx->transaction_date->diffInDays(now()) : 0;
                    $isOldTrip = $ageHours >= 24;
                    $tripBorderClass = $ageDays > 30 ? 'kd-trip-card--danger' : ($isOldTrip ? 'kd-trip-card--warn' : '');
                @endphp
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="kasir-card kd-trip-card {{ $tripBorderClass }} h-100 d-flex flex-column">
                        <div class="card-body d-flex flex-column flex-grow-1 p-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="min-w-0">
                                    <span class="kd-trip-code d-inline-block mb-1">{{ $tripCode }}</span>
                                    <div class="fw-bold text-dark">{{ $driverName }}</div>
                                </div>
                                <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                                    <span class="kasir-badge-info">
                                        <i class="fas fa-motorcycle"></i>
                                        <span>Mengantar</span>
                                    </span>
                                    @if($isOldTrip)
                                    <span class="{{ $ageDays > 30 ? 'kasir-badge-danger' : 'kasir-badge-warning' }}">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Sudah {{ max(1, $ageDays) }} hari</span>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                <span><i class="far fa-clock me-1"></i>{{ $firstTrx->transaction_date->format('d/m H:i') }} WIB</span>
                                <span>{{ $firstTrx->transaction_date->diffForHumans() }}</span>
                            </div>

                            <div class="kd-trip-list mb-3 flex-grow-1">
                                <div class="small fw-semibold text-muted mb-1">
                                    Daftar Pelanggan ({{ $tripTrx->count() }} Toko)
                                </div>
                                <ul class="list-unstyled mb-0 small">
                                    @foreach($tripTrx as $trx)
                                    <li class="d-flex justify-content-between align-items-start gap-2 py-1 {{ !$loop->last ? 'border-bottom' : '' }}">
                                        <span class="fw-semibold text-dark" title="{{ $trx->customer->name ?? 'Pelanggan Umum' }}">
                                            <i class="fas fa-map-marker-alt me-1" style="color: var(--kasir-primary);"></i>{{ $trx->customer->name ?? 'Pelanggan Umum' }}
                                        </span>
                                        <span class="fw-bold text-dark text-nowrap kasir-num">
                                            Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                                        </span>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="d-flex justify-content-between align-items-center small pt-2 border-top mb-3">
                                <span class="text-muted">Total muatan: <strong class="text-dark">{{ $totalQtyInTrip }} Item</strong></span>
                                <span class="fw-bold fs-6 kasir-num" style="color: var(--kasir-success);">
                                    Total: Rp {{ number_format($tripTotal, 0, ',', '.') }}
                                </span>
                            </div>

                            <div class="d-grid gap-2 mt-auto">
                                <button type="button" class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#{{ $modalTripId }}">
                                    <i class="fas fa-check-double"></i>
                                    <span>Selesaikan &amp; Input Real Payment</span>
                                </button>
                                @if(!empty($firstTrx->delivery_trip_code))
                                <a href="{{ route('kasir.pos.deliveryReceipt', $tripCode) }}" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-print"></i>
                                    <span>Cetak Surat Jalan</span>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================= --}}
    {{-- SECTION 2: RINGKASAN PIUTANG KASBON AKTIF                         --}}
    {{-- ================================================================= --}}
    @if(isset($kasbonByCustomer) && $kasbonByCustomer->isNotEmpty())
    <div class="kasir-card mb-4">
        <div class="kasir-section-header kasir-section-header--danger">
            <div>
                <h5 class="kasir-section-title">
                    <i class="fas fa-exclamation-triangle" style="color: var(--kasir-danger);"></i>
                    <span>RINGKASAN PIUTANG KASBON AKTIF</span>
                    <span class="kasir-badge-muted">
                        <i class="fas fa-store"></i>
                        <span>Gabungan Semua Cabang</span>
                    </span>
                </h5>
                <p class="kasir-section-subtitle">
                    {{ $totalKasbonCount }} struk dari {{ $kasbonByCustomer->count() }} pelanggan / mitra dalam bisnis ini.
                </p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="text-md-end">
                    <div class="small text-muted">Total Seluruh Tagihan</div>
                    <div class="fs-5 fw-bold kasir-num" style="color: var(--kasir-danger);">
                        Rp {{ number_format($totalKasbonAmount, 0, ',', '.') }}
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="shareGlobalKasbonWA()">
                    <i class="fab fa-whatsapp" style="color: var(--kasir-success);"></i>
                    <span>Rekap Semua (WA)</span>
                </button>
            </div>
        </div>

        <div class="kd-kasbon-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="input-group" style="max-width: 360px;">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="kasbonCustomerSearch" class="form-control form-control-sm" placeholder="Cari nama pelanggan atau nomor WA..." aria-label="Cari pelanggan kasbon">
            </div>
            <small class="text-muted">Diurutkan dari order paling tua</small>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive kd-kasbon-scroll">
                <table class="table kasir-table table-hover mb-0 align-middle" id="kasbonCustomerTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Nama Pelanggan / Mitra</th>
                            <th class="text-center">Struk Tertunda</th>
                            <th>Order Tertua</th>
                            <th class="text-end">Total Tagihan</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kasbonByCustomer as $groupKey => $transactions)
                        @php
                            $firstTx = $transactions->first();
                            $customerName = $firstTx->customer->name ?? 'Pelanggan Umum (Tanpa Nama)';
                            $totalTagihanCust = $transactions->sum('total_amount');
                            $oldestDate = $firstTx->transaction_date;
                            $oldestDays = $oldestDate ? (int) $oldestDate->diffInDays(now()) : 0;
                            $ageBadgeClass = $oldestDays > 90 ? 'kasir-badge-danger' : ($oldestDays >= 30 ? 'kasir-badge-warning' : 'kasir-badge-muted');
                            $modalId = 'modalGroup_' . $groupKey;
                            $branchesInGroup = $transactions->pluck('branch.name')->filter()->unique();
                        @endphp
                        <tr class="kasbon-customer-row" data-search="{{ strtolower($customerName . ' ' . ($firstTx->customer->phone ?? '')) }}">
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $customerName }}</div>
                                @if($firstTx->customer && $firstTx->customer->phone)
                                <div class="small text-muted">
                                    <i class="fab fa-whatsapp me-1" style="color: var(--kasir-success);"></i>{{ $firstTx->customer->phone }}
                                </div>
                                @endif
                                @if($branchesInGroup->isNotEmpty())
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    @foreach($branchesInGroup as $bName)
                                    <span class="kasir-badge-muted" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                                        <i class="fas fa-building"></i>
                                        <span>{{ $bName }}</span>
                                    </span>
                                    @endforeach
                                </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="kasir-badge-muted kasir-num">{{ $transactions->count() }} Struk</span>
                            </td>
                            <td>
                                <div class="text-dark kasir-num">{{ $oldestDate->format('d/m/Y') }}</div>
                                @if($oldestDays > 0)
                                <span class="{{ $ageBadgeClass }} mt-1">Sejak {{ $oldestDays }} hari lalu</span>
                                @else
                                <span class="kasir-badge-info mt-1">Hari Ini</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold kasir-num" style="color: var(--kasir-danger);">
                                Rp {{ number_format($totalTagihanCust, 0, ',', '.') }}
                            </td>
                            <td class="text-center pe-3">
                                <div class="kd-kasbon-actions">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                        <i class="fas fa-list-ul"></i>
                                        <span>Lihat &amp; Bayar</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalRekap_{{ $groupKey }}" title="Rekap Semua Transaksi {{ $customerName }} &amp; Kirim Struk ke WA">
                                        <i class="fab fa-whatsapp" style="color: var(--kasir-success);"></i>
                                        <span>Rekap Struk (WA)</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        <tr id="kasbonEmptySearchRow" class="d-none">
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="fas fa-search me-1"></i> Tidak ada pelanggan kasbon yang cocok dengan pencarian.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================= --}}
    {{-- SECTION 3: BARIS 3 KARTU KPI                                      --}}
    {{-- ================================================================= --}}
    <div class="row g-4">
        {{-- KPI 1: Saldo Kas Fisik Laci Hari Ini --}}
        <div class="col-lg-4 col-md-6">
            <div class="kasir-card kasir-kpi">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <div class="kasir-kpi-icon kasir-kpi-icon--success">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                    <h6 class="text-muted mb-0 fw-semibold small">Saldo Kas Fisik Laci (Hari Ini)</h6>
                                    <button type="button" class="btn btn-xs btn-outline-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalOpeningBalance" title="Atur Modal Awal Laci (Kas Kemarin)">
                                        <i class="fas fa-vault"></i>
                                        <span>Modal Awal</span>
                                    </button>
                                </div>
                                <div class="kasir-kpi-value" style="color: var(--kasir-success);">
                                    Rp {{ number_format($myNetCashToday ?? 0, 0, ',', '.') }}
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size: 0.76rem;">
                                    @if(isset($myOpeningCash) && $myOpeningCash > 0)
                                        <span class="text-dark fw-bold">Modal Awal: Rp {{ number_format($myOpeningCash, 0, ',', '.') }}</span> &middot;
                                    @endif
                                    Masuk Tunai: <strong style="color: var(--kasir-success);">+Rp {{ number_format($totalCashInToday ?? $mySalesToday ?? 0, 0, ',', '.') }}</strong>
                                    @if(isset($depositCashAmount) && $depositCashAmount > 0)
                                        <span class="fw-bold" style="color: var(--kasir-info);" title="Penerimaan titipan deposit pelanggan tunai">(Inc. Deposit: +Rp {{ number_format($depositCashAmount, 0, ',', '.') }})</span>
                                    @endif
                                    &middot; Keluar: <strong style="color: var(--kasir-danger);">-Rp {{ number_format($myExpensesToday ?? 0, 0, ',', '.') }}</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="pt-2 mt-2 border-top d-flex gap-2 flex-wrap">
                        <span class="kasir-badge-info kasir-num" title="Penerimaan Transfer Bank langsung ke Rekening Owner (Tidak masuk laci fisik)">
                            <i class="fas fa-university"></i>
                            <span>Transfer: Rp {{ number_format($nonCashTransfer ?? 0, 0, ',', '.') }}</span>
                        </span>
                        <span class="kasir-badge-muted kasir-num" title="Penerimaan DANA langsung ke E-Wallet Owner (Tidak masuk laci fisik)">
                            <i class="fas fa-mobile-alt"></i>
                            <span>DANA: Rp {{ number_format($nonCashDana ?? 0, 0, ',', '.') }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI 2: Transaksi Lunas --}}
        <div class="col-lg-4 col-md-6">
            <div class="kasir-card kasir-kpi">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-start gap-3 mb-2">
                        <div class="kasir-kpi-icon kasir-kpi-icon--primary">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-1 fw-semibold small">Transaksi Lunas</h6>
                            <div class="kasir-kpi-value">{{ number_format($myTransactionsToday ?? 0, 0, ',', '.') }}</div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.78rem;">
                                Penerimaan arus kas masuk tercatat hari ini
                            </small>
                        </div>
                    </div>
                    <div class="pt-2 mt-2 border-top d-flex flex-wrap justify-content-between gap-2 small text-muted">
                        <span>Tunai langsung: <strong class="text-dark kasir-num">Rp {{ number_format($cashSalesAmount ?? 0, 0, ',', '.') }}</strong></span>
                        <span>Non-tunai: <strong class="text-dark kasir-num">Rp {{ number_format($nonCashTotal ?? 0, 0, ',', '.') }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI 3: Produk Keluar Hari Ini --}}
        <div class="col-lg-4 col-md-12">
            <div class="kasir-card kasir-kpi">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="kasir-kpi-icon kasir-kpi-icon--accent">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="text-muted mb-0 fw-semibold small">Produk Keluar Hari Ini</h6>
                            <small class="text-muted" style="font-size: 0.75rem;">Top 5 produk terjual</small>
                        </div>
                    </div>

                    <div class="list-group list-group-flush flex-grow-1">
                        @forelse ($productsSoldToday as $product)
                        <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-1">
                            <span class="small fw-medium text-dark">{{ $product->name }}</span>
                            <span class="kasir-badge-muted kasir-num">{{ $product->total_quantity_sold }} Pcs</span>
                        </div>
                        @empty
                        <p class="text-center text-muted my-auto small">Belum ada produk terjual.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- SECTION 4: SALDO KAS AKTUAL HARI INI PER METODE PEMBAYARAN        --}}
    {{-- ================================================================= --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="kasir-card">
                <div class="kasir-section-header">
                    <h5 class="kasir-section-title mb-0">
                        <i class="fas fa-coins" style="color: var(--kasir-primary);"></i>
                        <span>Saldo Kas Aktual Hari Ini per Metode Pembayaran</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(isset($balancesByMethod) && $balancesByMethod->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table kasir-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Metode Pembayaran</th>
                                    <th class="text-end">Uang Masuk (Penjualan/Kasbon)</th>
                                    <th class="text-end">Uang Keluar (Expenses)</th>
                                    <th class="text-end pe-3">Saldo Akhir Aktual</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($balancesByMethod as $method => $data)
                                @php
                                    $mLower = strtolower($method);
                                    $mIcon = $mLower === 'cash' ? 'fa-money-bill-wave' : ($mLower === 'dana' ? 'fa-mobile-alt' : 'fa-university');
                                @endphp
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        <i class="fas {{ $mIcon }} me-2" style="color: var(--kasir-primary);"></i>{{ strtoupper(str_replace('-', ' ', $method)) }}
                                    </td>
                                    <td class="text-end fw-semibold kasir-num" style="color: var(--kasir-success);">
                                        + Rp {{ number_format($data['income'], 0, ',', '.') }}
                                    </td>
                                    <td class="text-end fw-semibold kasir-num" style="color: var(--kasir-danger);">
                                        - Rp {{ number_format($data['expense'], 0, ',', '.') }}
                                    </td>
                                    <td class="text-end pe-3">
                                        <span class="{{ $data['balance'] >= 0 ? 'kasir-badge-success' : 'kasir-badge-danger' }} kasir-num">
                                            Rp {{ number_format($data['balance'], 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted text-center my-4">Belum ada arus kas (masuk maupun keluar) hari ini.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- SECTION 5: RINCIAN PENJUALAN HARI INI PER METODE PEMBAYARAN       --}}
    {{-- ================================================================= --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="kasir-card">
                <div class="kasir-section-header">
                    <h5 class="kasir-section-title mb-0">
                        <i class="fas fa-chart-pie" style="color: var(--kasir-primary);"></i>
                        <span>Rincian Penjualan Hari Ini per Metode Pembayaran</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if(isset($salesByPaymentMethod) && $salesByPaymentMethod->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($salesByPaymentMethod as $method => $total)
                        @php
                            $smLower = strtolower($method);
                            $smIcon = $smLower === 'cash' ? 'fa-money-bill-wave' : ($smLower === 'dana' ? 'fa-mobile-alt' : 'fa-university');
                        @endphp
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span class="fw-bold">
                                <i class="fas {{ $smIcon }} me-2" style="color: var(--kasir-primary);"></i>{{ strtoupper(str_replace('-', ' ', $method)) }}
                            </span>
                            <span class="kasir-badge-info kasir-num fs-6">
                                Rp {{ number_format($total, 0, ',', '.') }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-muted text-center mb-0 py-2">Belum ada penjualan hari ini.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- SECTION 6: AKSI CEPAT (TETAP DI PALING BAWAH)                     --}}
    {{-- ================================================================= --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="kasir-card">
                <div class="kasir-section-header">
                    <h5 class="kasir-section-title mb-0">
                        <i class="fas fa-bolt" style="color: var(--kasir-accent);"></i>
                        <span>Aksi Cepat</span>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Mulai pekerjaan Anda dengan mengakses fitur yang paling sering digunakan.</p>
                    <div class="d-grid gap-2 d-md-flex flex-wrap">
                        <a href="{{ route('kasir.pos.index') }}" class="btn btn-lg btn-primary">
                            <i class="fas fa-desktop"></i>
                            <span>Mulai Transaksi (POS)</span>
                        </a>
                        <a href="{{ route('kasir.customers.create') }}" class="btn btn-lg btn-outline-secondary">
                            <i class="fas fa-user-plus"></i>
                            <span>Tambah Pelanggan</span>
                        </a>
                        <button type="button" class="btn btn-lg btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalManualDeposit">
                            <i class="fas fa-wallet"></i>
                            <span>Terima Deposit Pelanggan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{-- ============================================================= --}}
{{-- [BARU] MODAL PENYELESAIAN PENGIRIMAN DRIVER KOLEKTIF          --}}
{{-- ============================================================= --}}
@if(isset($activeTrips) && $activeTrips->isNotEmpty())
@foreach($activeTrips as $tripKey => $tripTrx)
@php
    $firstTrx = $tripTrx->first();
    $driverName = $firstTrx->driver->name ?? 'Driver';
    $tripCode = $firstTrx->delivery_trip_code ?? $tripKey;
    $modalTripId = 'modalTrip_' . \Illuminate\Support\Str::slug($tripKey);
    $totalTripAmount = $tripTrx->sum('total_amount');
@endphp
<div class="modal fade kd-settle-modal" id="{{ $modalTripId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog kd-settle-dialog modal-dialog-centered">
        <div class="modal-content kd-settle-content">
            <form action="{{ route('kasir.transactions.completeDeliveryTrip') }}" method="POST" class="kd-settle-form" novalidate>
                @csrf
                <input type="hidden" name="trip_code" value="{{ $tripCode }}">

                <div class="modal-header kd-settle-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="fas fa-truck-loading me-2"></i>Penyelesaian Pengiriman
                        </h5>
                        <div class="small opacity-90 mt-1 kd-settle-header-sub">
                            Driver <strong>{{ $driverName }}</strong> &middot; <span class="font-monospace">{{ $tripCode }}</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white kd-settle-close" data-bs-dismiss="modal" aria-label="Tutup modal"></button>
                </div>

                <div class="kd-settle-subheader">
                    <div class="kd-settle-subheader-hint">
                        <span class="text-dark fw-semibold">{{ $tripTrx->count() }} pesanan dalam pengiriman ini.</span>
                        <div class="small text-muted">Pilih metode pembayaran riil tiap pelanggan setelah driver kembali.</div>
                    </div>
                    <div class="text-md-end">
                        <div class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Total Tagihan</div>
                        <div class="fs-4 mb-0 text-danger fw-bold kasir-num">Rp {{ number_format($totalTripAmount, 0, ',', '.') }}</div>
                    </div>
                </div>

                <div class="modal-body kd-settle-body">
                    @foreach($tripTrx as $trx)
                    @php
                        $depositUsed = (float) ($trx->deposit_used ?? 0);
                        $effectivePayable = (float) $trx->effective_payable_amount;
                        $isFullDeposit = ($depositUsed > 0 && $effectivePayable <= 0);
                        $custDepositBal = (float) ($trx->customer->deposit_balance ?? 0);
                        $custName = $trx->customer->name ?? 'Pelanggan Umum';

                        $methodOptions = [];
                        if (isset($paymentMethods) && $paymentMethods->isNotEmpty()) {
                            foreach ($paymentMethods as $pm) {
                                $slugLower = strtolower($pm->slug);
                                $iconClass = 'fa-credit-card';
                                if (str_contains($slugLower, 'cash')) $iconClass = 'fa-money-bill-wave';
                                elseif (str_contains($slugLower, 'dana')) $iconClass = 'fa-wallet';
                                elseif (str_contains($slugLower, 'transfer')) $iconClass = 'fa-university';
                                elseif (str_contains($slugLower, 'qris')) $iconClass = 'fa-qrcode';

                                $methodOptions[] = [
                                    'slug' => $pm->slug,
                                    'label' => $pm->name,
                                    'option_text' => $pm->name . ' (Lunas)',
                                    'selected' => ($slugLower === 'cash'),
                                    'icon' => $iconClass,
                                    'is_kasbon' => false,
                                ];
                            }
                        } else {
                            $methodOptions = [
                                ['slug' => 'cash', 'label' => 'Cash', 'option_text' => 'Cash / Tunai (Lunas)', 'selected' => true, 'icon' => 'fa-money-bill-wave', 'is_kasbon' => false],
                                ['slug' => 'transfer-bank', 'label' => 'Transfer', 'option_text' => 'Transfer Bank (Lunas)', 'selected' => false, 'icon' => 'fa-university', 'is_kasbon' => false],
                                ['slug' => 'dana', 'label' => 'DANA', 'option_text' => 'DANA (Lunas)', 'selected' => false, 'icon' => 'fa-wallet', 'is_kasbon' => false],
                            ];
                        }

                        $denominations = [20000, 50000, 100000, 150000, 200000, 300000, 500000, 1000000];
                        $quickAbove = array_values(array_filter($denominations, fn($v) => $v > (int) $effectivePayable));
                        $quickNominals = array_slice($quickAbove, 0, 3);
                    @endphp
                    <div class="kd-settle-card" id="settle_card_{{ $trx->id }}" data-trx-id="{{ $trx->id }}" data-payable="{{ $effectivePayable }}" data-customer-name="{{ $custName }}" data-full-deposit="{{ $isFullDeposit ? '1' : '0' }}">
                        <div class="kd-settle-row">
                            {{-- Kolom 1: Info Struk, Pelanggan & Barang --}}
                            <div class="kd-settle-col-info">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <span class="fw-bold kasir-num" style="color: var(--kasir-primary);">
                                        #{{ str_pad($trx->id, 6, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <strong class="fs-6 text-dark">{{ $custName }}</strong>
                                    @if($custDepositBal > 0)
                                        <span class="kasir-badge kasir-badge-info" title="Saldo deposit saat ini">
                                            <i class="fas fa-wallet"></i> Deposit: Rp {{ number_format($custDepositBal, 0, ',', '.') }}
                                        </span>
                                    @endif
                                </div>
                                @if($trx->customer && $trx->customer->phone)
                                    <div class="small text-muted mb-1">
                                        <i class="fas fa-phone me-1"></i>{{ $trx->customer->phone }}
                                    </div>
                                @endif
                                <ul class="list-unstyled mb-0 small text-muted">
                                    @foreach($trx->details as $item)
                                        <li>&bull; {{ $item->product->name ?? 'Item' }} ({{ $item->quantity }} {{ $item->product->unit ?? 'pcs' }})</li>
                                    @endforeach
                                </ul>
                            </div>

                            {{-- Kolom 2: Nilai Tagihan --}}
                            <div class="kd-settle-col-amount text-md-end kasir-num">
                                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Nilai Tagihan</div>
                                @if($depositUsed > 0)
                                    <div class="small text-muted text-decoration-line-through">Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</div>
                                    <span class="kasir-badge kasir-badge-success mb-1">
                                        Deposit: -Rp {{ number_format($depositUsed, 0, ',', '.') }}
                                    </span>
                                    @if($isFullDeposit)
                                        <div class="fw-bold text-primary fs-5">Rp 0</div>
                                    @else
                                        <div class="fw-bold fs-5 text-danger">Sisa: Rp {{ number_format($effectivePayable, 0, ',', '.') }}</div>
                                    @endif
                                @else
                                    <div class="fw-bold text-danger fs-5">
                                        Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                                    </div>
                                @endif
                            </div>

                            {{-- Kolom 3: Metode Pembayaran Riil (Segmen + Select Sumber Kebenaran) --}}
                            <div class="kd-settle-col-method">
                                @if($isFullDeposit)
                                    <div class="p-3 rounded bg-light border border-primary text-center">
                                        <span class="badge bg-primary text-white mb-1"><i class="fas fa-check-circle me-1"></i> Lunas via Deposit</span>
                                        <input type="hidden" name="payments[{{ $trx->id }}]" value="deposit">
                                        <div class="small text-muted">Barang tinggal diantar tanpa penagihan uang.</div>
                                    </div>
                                @else
                                    {{-- Select tersembunyi sebagai sumber kebenaran payload ke server --}}
                                    <select name="payments[{{ $trx->id }}]" id="pay_sel_{{ $trx->id }}" class="kd-settle-pay-select d-none" data-trx-id="{{ $trx->id }}" aria-label="Metode pembayaran riil struk #{{ str_pad($trx->id, 6, '0', STR_PAD_LEFT) }}" required>
                                        <option value="" disabled>-- Pilih Pembayaran Riil --</option>
                                        @foreach($methodOptions as $opt)
                                            <option value="{{ $opt['slug'] }}" data-label="{{ $opt['label'] }}" {{ $opt['selected'] ? 'selected' : '' }}>
                                                {{ $opt['option_text'] }}
                                            </option>
                                        @endforeach
                                        <option value="kasbon" data-label="Kasbon" class="text-danger fw-bold">
                                            TETAP KASBON (Belum Lunas)
                                        </option>
                                    </select>

                                    <div class="kd-settle-segments" role="group" aria-label="Pilih metode pembayaran untuk {{ $custName }}">
                                        @foreach($methodOptions as $opt)
                                            <button type="button"
                                                    class="kd-settle-seg-btn {{ $opt['selected'] ? 'is-active' : '' }}"
                                                    data-trx-id="{{ $trx->id }}"
                                                    data-pay-value="{{ $opt['slug'] }}"
                                                    data-pay-label="{{ $opt['label'] }}"
                                                    aria-pressed="{{ $opt['selected'] ? 'true' : 'false' }}">
                                                <i class="fas {{ $opt['icon'] }}"></i>
                                                <span>{{ $opt['label'] }}</span>
                                            </button>
                                        @endforeach
                                        <button type="button"
                                                class="kd-settle-seg-btn is-kasbon"
                                                data-trx-id="{{ $trx->id }}"
                                                data-pay-value="kasbon"
                                                data-pay-label="Tetap Kasbon"
                                                aria-pressed="false">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span>Tetap Kasbon</span>
                                        </button>
                                    </div>

                                    {{-- Select waktu terima uang tersembunyi + tampilan segmen 2 opsi penuh --}}
                                    <select name="settlement_date_modes[{{ $trx->id }}]" id="date_mode_sel_{{ $trx->id }}" class="kd-settle-date-select d-none" data-trx-id="{{ $trx->id }}" aria-label="Waktu uang diterima">
                                        <option value="today" selected>Uang Diterima Hari Ini (Masuk Laci Kasir)</option>
                                        <option value="nota_date">Koreksi Lupa Update (Tgl Nota: {{ $trx->transaction_date->format('d/m') }})</option>
                                    </select>

                                    <div class="kd-settle-date-wrap" id="date_wrap_{{ $trx->id }}">
                                        <div class="kd-settle-date-group" role="group" aria-label="Alokasi waktu pembukuan uang diterima">
                                            <button type="button"
                                                    class="kd-settle-date-btn is-active"
                                                    data-trx-id="{{ $trx->id }}"
                                                    data-date-value="today"
                                                    aria-pressed="true">
                                                <i class="fas fa-calendar-check"></i>
                                                <span>Uang Diterima Hari Ini (Masuk Laci Kasir)</span>
                                            </button>
                                            <button type="button"
                                                    class="kd-settle-date-btn is-warn"
                                                    data-trx-id="{{ $trx->id }}"
                                                    data-date-value="nota_date"
                                                    aria-pressed="false">
                                                <i class="fas fa-history"></i>
                                                <span>Koreksi Lupa Update (Tgl Nota: {{ $trx->transaction_date->format('d/m') }})</span>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Peringatan merah jika Tetap Kasbon dipilih --}}
                        <div class="kd-settle-kasbon-alert d-none" id="kasbon_alert_{{ $trx->id }}" role="status">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Belum lunas: nominal ini masuk piutang kasbon <strong>{{ $custName }}</strong>.</span>
                        </div>

                        {{-- Peringatan validasi jika belum memilih metode pembayaran --}}
                        <div class="kd-settle-unselected-msg d-none" id="unselected_msg_{{ $trx->id }}" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Pilih metode pembayaran terlebih dahulu.</span>
                        </div>

                        {{-- Baris Aksi Kartu: Catatan & Tombol Deposit --}}
                        <div class="kd-settle-card-actions">
                            <button type="button"
                                    class="btn kd-settle-note-toggle"
                                    data-trx-id="{{ $trx->id }}"
                                    aria-expanded="false"
                                    aria-controls="note_wrap_{{ $trx->id }}">
                                <i class="fas fa-sticky-note me-1"></i> + Catatan
                            </button>

                            @if($trx->customer_id)
                                <button type="button"
                                        class="btn kd-settle-deposit-btn btn-toggle-deposit"
                                        id="btn_dep_{{ $trx->id }}"
                                        data-trx-id="{{ $trx->id }}"
                                        aria-expanded="false"
                                        aria-controls="deposit_panel_{{ $trx->id }}">
                                    <i class="fas fa-piggy-bank me-1"></i> + Deposit Pelanggan
                                </button>
                            @endif

                            <div class="w-100 mt-1 d-none kd-settle-note-wrap" id="note_wrap_{{ $trx->id }}">
                                <label for="note_inp_{{ $trx->id }}" class="form-label small fw-semibold text-muted mb-1">Catatan (Opsional)</label>
                                <input type="text" id="note_inp_{{ $trx->id }}" name="notes[{{ $trx->id }}]" class="form-control" placeholder="Ket...">
                            </div>
                        </div>

                        {{-- Panel Deposit Pelanggan Full-Width (hanya muncul untuk pelanggan terdaftar) --}}
                        @if($trx->customer_id)
                            <div class="kd-settle-deposit-panel d-none" id="deposit_panel_{{ $trx->id }}" data-trx-id="{{ $trx->id }}">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 kd-settle-dep-head">
                                    <div>
                                        <h6 class="mb-0 fw-bold text-nowrap" style="color: #0f766e;">
                                            <i class="fas fa-wallet me-2"></i>Titip Deposit Pelanggan &middot; {{ $custName }}
                                        </h6>
                                        <span class="small text-muted kd-settle-dep-subtitle">Kelebihan bayar otomatis tersimpan ke saldo deposit pelanggan</span>
                                    </div>
                                    <button type="button"
                                            class="btn btn-outline-secondary kd-settle-dep-done-btn"
                                            data-trx-id="{{ $trx->id }}"
                                            aria-label="Selesai mengetik dan tutup keyboard">
                                        <i class="fas fa-check me-1"></i> Selesai
                                    </button>
                                </div>

                                {{-- Hidden input nilai deposit bersih yang dikirim ke server --}}
                                <input type="hidden" name="deposits[{{ $trx->id }}]" id="deposit_val_{{ $trx->id }}" value="0">

                                {{-- 3 Kotak Informasi Utama --}}
                                <div class="kd-settle-dep-grid">
                                    <div class="kd-settle-dep-box kd-settle-dep-tagihan-box">
                                        <span class="kd-settle-dep-label">Tagihan Order Ini</span>
                                        <div class="kd-settle-dep-val text-danger kasir-num">
                                            Rp {{ number_format($effectivePayable, 0, ',', '.') }}
                                        </div>
                                    </div>
                                    <div class="kd-settle-dep-box kd-settle-dep-saldo-box">
                                        <span class="kd-settle-dep-label">Saldo Deposit Sekarang</span>
                                        <div class="kd-settle-dep-val kasir-num" style="color: #0f766e;">
                                            Rp {{ number_format($custDepositBal, 0, ',', '.') }}
                                        </div>
                                    </div>
                                    <div class="kd-settle-dep-box kd-settle-dep-input-box">
                                        <label for="driver_money_{{ $trx->id }}" class="kd-settle-dep-label">Uang Diterima Dari Driver</label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-white fw-bold" style="color: #0f766e;">Rp</span>
                                            <input type="text"
                                                   inputmode="numeric"
                                                   pattern="[0-9]*"
                                                   enterkeyhint="done"
                                                   autocomplete="off"
                                                   autocapitalize="off"
                                                   spellcheck="false"
                                                   class="form-control fw-bold kasir-num kd-settle-driver-input input-driver-money"
                                                   id="driver_money_{{ $trx->id }}"
                                                   data-trx-id="{{ $trx->id }}"
                                                   data-payable="{{ $effectivePayable }}"
                                                   data-deposit-bal="{{ $custDepositBal }}"
                                                   placeholder="0"
                                                   aria-describedby="driver_help_{{ $trx->id }}">
                                        </div>
                                        <small id="driver_help_{{ $trx->id }}" class="text-muted d-block mt-1" style="font-size: 0.82rem;">
                                            Ketik total uang tunai yang diserahkan driver
                                        </small>
                                    </div>
                                </div>

                                {{-- Tombol Cepat Nominal --}}
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-3 kd-settle-quick-row">
                                    <span class="small fw-semibold text-muted me-1 flex-shrink-0">Isi cepat:</span>
                                    <button type="button"
                                            class="btn kd-settle-quick-btn kasir-num"
                                            data-trx-id="{{ $trx->id }}"
                                            data-amount="{{ (int) round($effectivePayable) }}">
                                        Uang pas (Rp {{ number_format($effectivePayable, 0, ',', '.') }})
                                    </button>
                                    @foreach($quickNominals as $qNom)
                                        <button type="button"
                                                class="btn kd-settle-quick-btn kasir-num"
                                                data-trx-id="{{ $trx->id }}"
                                                data-amount="{{ $qNom }}">
                                            Rp {{ number_format($qNom, 0, ',', '.') }}
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Panel Ringkasan Perhitungan Otomatis (3 kotak putih) --}}
                                <div class="summary-deposit-live mt-3" id="deposit_summary_{{ $trx->id }}"></div>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>

                <div class="modal-footer kd-settle-footer flex-column align-items-stretch gap-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="kd-settle-summary-bar" data-summary-bar>
                            <div class="kd-settle-sum-item">
                                <span class="kd-settle-sum-label">Masuk Laci (Cash)</span>
                                <span class="kd-settle-sum-val text-success kasir-num" data-sum-cash>Rp 0</span>
                            </div>
                            <div class="kd-settle-sum-item" data-sum-noncash-wrap style="display: contents;"></div>
                            <div class="kd-settle-sum-item">
                                <span class="kd-settle-sum-label">Jadi Kasbon</span>
                                <span class="kd-settle-sum-val text-danger kasir-num" data-sum-kasbon>Rp 0</span>
                            </div>
                            <div class="kd-settle-sum-item d-none" data-sum-deposit-wrap>
                                <span class="kd-settle-sum-label">Deposit Baru</span>
                                <span class="kd-settle-sum-val kasir-num" style="color: #0f766e;" data-sum-deposit>+Rp 0</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <button type="button" class="btn btn-outline-secondary px-4" style="min-height: 44px; font-weight: 600;" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn kd-settle-submit-btn">
                                <i class="fas fa-check-circle me-1"></i> Simpan Semua Pelunasan &amp; Selesaikan
                            </button>
                        </div>
                    </div>

                    <div class="pt-1 border-top kd-settle-footer-info">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i> Transaksi lunas otomatis tercatat ke <strong>Cash Flow</strong>. Transaksi 'Tetap Kasbon' masuk ke piutang kasbon di bawah.
                        </small>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif

{{-- ============================================================= --}}
{{-- [BARU] MODAL RINCIAN STRUK PER PELANGGAN (DENGAN CHECKBOX) --}}
{{-- ============================================================= --}}
@if(isset($kasbonByCustomer) && $kasbonByCustomer->isNotEmpty())
@foreach($kasbonByCustomer as $groupKey => $transactions)
@php
$firstTx = $transactions->first();
$customerName = $firstTx->customer->name ?? 'Pelanggan Umum';
$totalTagihanCust = $transactions->sum('total_amount');
$oldestDate = $firstTx ? $firstTx->transaction_date : now();
$modalId = 'modalGroup_' . $groupKey;
@endphp
<div class="modal fade kasbon-bulk-modal"
     id="{{ $modalId }}"
     data-group-key="{{ $groupKey }}"
     data-total-pending="{{ (int) round($totalTagihanCust) }}"
     data-total-count="{{ $transactions->count() }}"
     tabindex="-1"
     aria-hidden="true">
    <div class="modal-dialog kd-kasbon-dialog modal-dialog-centered">
        <div class="modal-content kd-kasbon-content">
            {{-- FORM DIMULAI DARI SINI, MEMBUNGKUS SELURUH MODAL --}}
            <form action="{{ route('kasir.transactions.bulkMarkAsPaid') }}" method="POST" enctype="multipart/form-data" id="bulk_form_{{ $groupKey }}" class="kasbon-bulk-form kd-kasbon-form" data-group-key="{{ $groupKey }}">
                @csrf
                <div class="modal-header kd-kasbon-header">
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="fas fa-user-clock me-2"></i>Daftar Kasbon: <strong>{{ $customerName }}</strong>
                    </h5>
                    <button type="button" class="btn-close btn-close-white kd-settle-close" data-bs-dismiss="modal" aria-label="Tutup modal"></button>
                </div>

                <div class="kd-kasbon-subheader d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="text-muted">Total {{ $transactions->count() }} transaksi tertunda.</span>
                        @if($firstTx->customer && $firstTx->customer->phone)
                        <div class="small text-muted"><i class="fab fa-whatsapp text-success me-1"></i> {{ $firstTx->customer->phone }}</div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold" style="min-height: 38px;" onclick="shareCustomerKasbonWA('{{ $groupKey }}')" title="Kirim Tagihan ke WhatsApp Pelanggan">
                            <i class="fab fa-whatsapp me-1"></i> Kirim WA Tagihan
                        </button>
                        <h5 class="mb-0 text-danger fw-bold ms-2 kasir-num">Total tertunda: Rp {{ number_format($totalTagihanCust, 0, ',', '.') }}</h5>
                    </div>
                </div>

                {{-- Area scroll berisi tabel daftar nota + form pembayaran --}}
                <div class="modal-body p-0 kd-kasbon-scroll-area">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top" style="z-index: 2;">
                                <tr>
                                    {{-- Checkbox Pilih Semua (target sentuh >= 44px) --}}
                                    <th class="ps-3" style="width: 52px;">
                                        <span class="kd-kasbon-chk-wrap">
                                            <input class="form-check-input select-all-btn m-0" type="checkbox" data-target=".chk-{{ $groupKey }}" data-group-key="{{ $groupKey }}" aria-label="Pilih semua nota kasbon" style="transform: scale(1.2);">
                                        </span>
                                    </th>
                                    <th>No. Struk</th>
                                    <th>Cabang</th>
                                    <th>Tanggal Order</th>
                                    <th>Kasir</th>
                                    <th>Catatan</th>
                                    <th class="text-end pe-4">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $kasbon)
                                @php
                                    $isMyBranch = (int) $kasbon->branch_id === (int) (auth()->user()->branch_id ?? 0);
                                    $rawNominal = (int) round($kasbon->total_amount);
                                @endphp
                                <tr class="kd-kasbon-row" data-amount="{{ $rawNominal }}">
                                    <td class="ps-3">
                                        {{-- Checkbox Individual (target sentuh >= 44px) --}}
                                        <span class="kd-kasbon-chk-wrap">
                                            <input class="form-check-input chk-{{ $groupKey }} item-checkbox m-0"
                                                   type="checkbox"
                                                   name="transaction_ids[]"
                                                   value="{{ $kasbon->id }}"
                                                   data-amount="{{ $rawNominal }}"
                                                   data-group-key="{{ $groupKey }}"
                                                   aria-label="Pilih struk #{{ str_pad($kasbon->id, 6, '0', STR_PAD_LEFT) }}"
                                                   style="transform: scale(1.2);">
                                        </span>
                                    </td>
                                    <td class="fw-bold text-primary kasir-num">
                                        #{{ str_pad($kasbon->id, 6, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td>
                                        <span class="kasir-badge {{ $isMyBranch ? 'kasir-badge-info' : 'kasir-badge-warning' }}">
                                            <i class="fas fa-store"></i> {{ $kasbon->branch->name ?? 'Cabang Asal' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="kasir-num">{{ $kasbon->transaction_date->format('d/m/Y') }}</span><br>
                                        <small class="text-muted kasir-num">{{ $kasbon->transaction_date->format('H:i') }} WIB</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-semibold" style="font-size: 0.8rem;">
                                            <i class="fas fa-user text-primary me-1"></i>{{ $kasbon->createdBy->name ?? 'Kasir' }}
                                        </span>
                                        @if($kasbon->driver)
                                            <br><small class="text-muted" style="font-size: 0.72rem;"><i class="fas fa-motorcycle text-info me-1"></i>{{ $kasbon->driver->name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted fst-italic">{{ $kasbon->notes ?? '-' }}</small>
                                    </td>
                                    <td class="text-end fw-bold pe-4 kasir-num kd-kasbon-amount-cell">
                                        Rp {{ number_format($kasbon->total_amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Panel Opsi Pelunasan Fleksibel (tetap di area scroll agar tombol bayar menempel sticky di bawah) --}}
                    <div class="bg-light p-3 border-top">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-3">
                                <label for="bulk_payment_method_{{ $groupKey }}" class="small fw-bold text-muted mb-1">Metode Bayar:</label>
                                <select name="payment_method" id="bulk_payment_method_{{ $groupKey }}" class="form-select form-select-sm fw-bold border-primary kd-kasbon-method-sel" data-group-key="{{ $groupKey }}" required>
                                    <option value="cash">Cash / Tunai</option>
                                    <option value="transfer-bank">Transfer Bank</option>
                                    <option value="dana">DANA</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="bulk_settlement_date_mode_{{ $groupKey }}" class="small fw-bold text-muted mb-1">Alokasi Tanggal Pembukuan:</label>
                                <select name="settlement_date_mode" id="bulk_settlement_date_mode_{{ $groupKey }}" class="form-select form-select-sm kd-kasbon-date-sel" data-group-key="{{ $groupKey }}">
                                    <option value="today" id="bulk_opt_today_{{ $groupKey }}" selected>Uang Baru Diterima Hari Ini (Masuk Laci / Kas Hari Ini)</option>
                                    <option value="nota_date" id="bulk_opt_nota_date_{{ $groupKey }}">Koreksi Lupa Update (Uang Sudah Diterima Saat Tanggal Nota)</option>
                                </select>
                                <div id="bulk_warning_nota_{{ $groupKey }}" class="text-danger small mt-1 fw-bold d-none" style="font-size: 0.72rem;">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Perhatian: Memilih opsi ini tidak akan menambah catatan Saldo Laci hari ini karena uang dianggap sudah masuk pada tanggal nota dibuat.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="bulk_notes_{{ $groupKey }}" class="small fw-bold text-muted mb-1">Catatan Pelunasan (Opsional):</label>
                                <input type="text" id="bulk_notes_{{ $groupKey }}" name="settlement_notes" class="form-control form-control-sm kd-kasbon-note-inp" placeholder="Misal: Bukti transfer terlampir">
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <label for="bulk_payment_proof_{{ $groupKey }}" class="small fw-bold text-dark mb-1 d-flex justify-content-between align-items-center">
                                    <span><i class="fab fa-whatsapp text-success me-1"></i> Bukti Pembayaran / Transfer Non-Tunai (WhatsApp):</span>
                                    <span id="bulk_proof_required_badge_{{ $groupKey }}"><span class="badge bg-light text-muted border">Opsional (Cash)</span></span>
                                </label>
                                <input type="file" name="payment_proof" id="bulk_payment_proof_{{ $groupKey }}" class="form-control form-control-sm kd-kasbon-proof-inp" data-group-key="{{ $groupKey }}" accept="image/*">
                                <div id="bulk_proof_help_{{ $groupKey }}" class="text-muted" style="font-size: 0.72rem;">
                                    *Opsional untuk Cash. Cukup upload <strong>1 file bukti</strong> dari galeri tablet untuk melunasi seluruh nota yang dicentang di atas.
                                </div>
                                {{-- Alert Box Penguncian Tombol Pelunasan --}}
                                <div id="bulk_proof_alert_{{ $groupKey }}" class="alert alert-danger alert-permanent py-2 px-3 mt-2 mb-0 d-none" style="font-size: 0.78rem;">
                                    <i class="fas fa-lock me-1"></i> <strong>Tombol Pelunasan Terkunci:</strong> Pembayaran melalui <span class="fw-bold text-uppercase" id="bulk_proof_method_name_{{ $groupKey }}">Non-Tunai</span> WAJIB melampirkan foto / screenshot bukti transfer WhatsApp. Silakan pilih file bukti dari galeri tablet di atas untuk membuka tombol pelunasan.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer Sticky: Bar Ringkasan Terpilih + Tombol Pelunasan --}}
                <div class="modal-footer kd-kasbon-footer flex-column align-items-stretch gap-2">
                    <div id="bulk_selected_summary_{{ $groupKey }}" class="kd-kasbon-summary-bar" aria-live="polite">
                        <span class="fw-semibold text-dark small" data-kasbon-count>
                            0 dari {{ $transactions->count() }} nota dipilih
                        </span>
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="small text-muted fw-semibold">Total dipilih</span>
                            <span class="kd-kasbon-summary-total kasir-num" data-kasbon-selected-total>Rp 0</span>
                        </div>
                        <div class="small text-muted kasir-num" data-kasbon-remaining>
                            Sisa kasbon setelah pelunasan: <strong>Rp {{ number_format($totalTagihanCust, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted kd-kasbon-footer-hint">
                            <i class="fas fa-shield-alt text-primary me-1"></i> Opsi "Koreksi" tidak akan mengotori kas laci hari ini.
                        </small>
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-outline-secondary" style="min-height: 48px;" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-secondary fw-bold kd-kasbon-submit-btn" id="bulk_btn_submit_{{ $groupKey }}" disabled>
                                <i class="fas fa-ban me-1"></i> Pilih nota yang akan dilunasi
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================= --}}
{{-- MODAL STRUK REKAP TAGIHAN PER PELANGGAN (KASAE, DLL) --}}
{{-- ============================================================= --}}
@php
    $rekapTotal = $transactions->sum('total_amount');
    $rekapOldest = $transactions->first() ? $transactions->first()->transaction_date : now();
    $rekapNewest = $transactions->last() ? $transactions->last()->transaction_date : now();
@endphp
<div class="modal fade" id="modalRekap_{{ $groupKey }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="fab fa-whatsapp me-2"></i> Rekap Struk Tagihan: {{ $customerName }}
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 bg-light text-center" style="max-height: 75vh; overflow-y: auto;">
                {{-- KERTAS STRUK TERMAL (80mm) YANG AKAN DICAPTURE KE WHATSAPP --}}
                <div class="rekap-receipt-card text-start mx-auto p-3 bg-white border shadow-sm" id="rekap-area-{{ $groupKey }}" style="max-width: 80mm; font-family: 'Courier New', Courier, monospace; font-size: 9.5pt; color: #111;">
                    <div class="text-center pb-2 mb-2 border-bottom border-2 border-dark">
                        <h5 class="fw-bold mb-0">ADIN CRYSTAL</h5>
                        <div class="small fw-semibold">Es Kristal Premium</div>
                        <div class="small text-muted" style="font-size: 8pt;">Telp: 087811192774</div>
                    </div>

                    <div class="text-center bg-danger text-white fw-bold py-1 mb-2 small rounded">
                        STRUK REKAP TAGIHAN KASBON
                    </div>

                    <div class="small mb-2" style="font-size: 9pt;">
                        <table class="w-100">
                            <tr>
                                <td style="width: 38%; font-weight: 600;">Pelanggan</td>
                                <td>: <strong>{{ $customerName }}</strong></td>
                            </tr>
                            @if($firstTx->customer && $firstTx->customer->phone)
                            <tr>
                                <td style="font-weight: 600;">No. WhatsApp</td>
                                <td>: {{ $firstTx->customer->phone }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="font-weight: 600;">Tgl Rekap</td>
                                <td>: {{ now()->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600;">Total Struk</td>
                                <td>: {{ $transactions->count() }} Transaksi Belum Lunas</td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600;">Periode</td>
                                <td>: {{ $rekapOldest->format('d/m/Y') }} s/d {{ $rekapNewest->format('d/m/Y') }}</td>
                            </tr>
                        </table>
                    </div>

                    <div class="py-2 my-2" style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; font-size: 8.5pt;">
                        <div class="fw-bold mb-1 text-center">--- RINCIAN TRANSAKSI TERTUNDA ---</div>
                        @foreach($transactions as $tIdx => $t)
                        <div class="mb-2 {{ !$loop->last ? 'border-bottom border-light pb-1' : '' }}">
                            <div class="d-flex justify-content-between fw-bold">
                                <span>{{ $tIdx + 1 }}. Struk #{{ str_pad($t->id, 6, '0', STR_PAD_LEFT) }}</span>
                                <span>{{ $t->transaction_date->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="text-muted" style="font-size: 8pt; padding-left: 10px;">
                                @if($t->branch) Cabang: <strong>{{ $t->branch->name }}</strong> | @endif Kasir: <strong>{{ $t->createdBy->name ?? 'Kasir' }}</strong>
                                @if($t->driver) | Driver: {{ $t->driver->name }} @endif
                            </div>
                            @if($t->details && $t->details->isNotEmpty())
                                @foreach($t->details as $det)
                                <div class="d-flex justify-content-between text-muted" style="padding-left: 10px;">
                                    <span>&bull; {{ $det->product->name ?? 'Produk' }} (x{{ $det->quantity }})</span>
                                    <span>Rp {{ number_format($det->total_price ?? ($det->quantity * $det->unit_price), 0, ',', '.') }}</span>
                                </div>
                                @endforeach
                            @endif
                            @if($t->notes)
                            <div class="text-muted fst-italic" style="font-size: 7.5pt; padding-left: 10px;">Ket: {{ $t->notes }}</div>
                            @endif
                            <div class="d-flex justify-content-between fw-semibold text-danger" style="padding-left: 10px;">
                                <span>Subtotal:</span>
                                <span>Rp {{ number_format($t->total_amount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="pt-1">
                        <div class="d-flex justify-content-between align-items-center fs-6 fw-bold border-top border-2 border-dark pt-1">
                            <span>TOTAL TAGIHAN:</span>
                            <span class="text-danger fs-5">Rp {{ number_format($rekapTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="text-center text-danger border border-danger p-1 my-2 fw-bold small" style="border-style: dashed !important; font-size: 8.5pt;">
                        STATUS: BELUM LUNAS (KASBON)
                    </div>

                    <div class="text-center mt-2 text-muted" style="font-size: 8pt;">
                        Mohon kesediaannya untuk melakukan pembayaran.<br>
                        Dapat melalui Tunai / Transfer.<br>
                        Terima kasih atas kerjasamanya!
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex flex-column gap-2">
                <button type="button" class="btn btn-success fw-bold w-100 py-2 shadow-sm" id="btnShareStruk_{{ $groupKey }}" onclick="shareStrukRekapPDF('{{ $groupKey }}', '{{ $customerName }}', '{{ $firstTx->customer->phone ?? '' }}')">
                    <i class="fas fa-file-pdf me-1"></i> Bagikan Struk Tagihan (PDF / WhatsApp)
                </button>
                <div class="d-flex justify-content-between w-100 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-outline-success btn-sm fw-semibold" onclick="sendChatWAText('{{ $groupKey }}', '{{ $customerName }}', '{{ $firstTx->customer->phone ?? '' }}', {{ $rekapTotal }})">
                        <i class="fab fa-whatsapp me-1"></i> Kirim Chat Teks WA
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="printStrukRekap('rekap-area-{{ $groupKey }}')">
                        <i class="fas fa-print me-1"></i> Cetak Struk
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach
@endif

    {{-- Modal Input Modal Awal Laci (Kas Kemarin) --}}
    <div class="modal fade" id="modalOpeningBalance" tabindex="-1" aria-labelledby="modalOpeningBalanceLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('kasir.set_opening_balance') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold" id="modalOpeningBalanceLabel">
                            <i class="fas fa-vault me-2"></i> Modal Awal Laci (Kas Kemarin)
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info alert-permanent py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Input uang tunai sisa kemarin atau uang kembalian yang ditinggalkan di laci kasir. Nilai ini akan otomatis menambah <strong>Saldo Kas Laci (Fisik)</strong> kasir hari ini, tanpa menambah Laba Shift.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nominal Modal Awal (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" step="any" min="0" name="amount" class="form-control fw-bold fs-5 text-success" value="{{ $myOpeningCash ?? 0 }}" required placeholder="0">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold">Catatan (Opsional)</label>
                            <textarea name="notes" rows="2" class="form-control" placeholder="Contoh: Sisa kas kecil dari shift kemarin...">{{ $myOpeningNotes ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="fas fa-save me-1"></i> Simpan Modal Awal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Penerimaan Deposit Pelanggan Mandiri --}}
    <div class="modal fade" id="modalManualDeposit" tabindex="-1" aria-labelledby="modalManualDepositLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('kasir.transactions.customerDeposit') }}" method="POST">
                    @csrf
                    <div class="modal-header text-white" style="background: var(--kasir-gradient);">
                        <h5 class="modal-title fw-bold" id="modalManualDepositLabel">
                            <i class="fas fa-wallet me-2"></i> Penerimaan Deposit Pelanggan
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info alert-permanent py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            Penyetoran deposit tunai otomatis menambah <strong>Kas Fisik Laci</strong> kasir hari ini, berlaku lintas cabang, dan tersimpan sebagai saldo belanja pelanggan tanpa mencemari omset penjualan ataupun laba rugi.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Pelanggan <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select border-primary fw-bold" required>
                                <option value="" disabled selected>-- Pilih Pelanggan --</option>
                                @if(isset($customers) && $customers->isNotEmpty())
                                    @foreach($customers as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->name }} (Saldo: Rp {{ number_format($c->deposit_balance ?? 0, 0, ',', '.') }})
                                    </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nominal Setoran (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" step="any" min="1000" name="amount" class="form-control fw-bold fs-5 text-primary" required placeholder="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash" selected>Cash / Tunai (Masuk Laci Kasir)</option>
                                <option value="transfer-bank">Transfer Bank (Rekening Owner)</option>
                                <option value="dana">DANA (E-Wallet Owner)</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold">Catatan (Opsional)</label>
                            <textarea name="notes" rows="2" class="form-control" placeholder="Contoh: Titipan saldo belanja mingguan..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-custom-primary px-4 fw-bold">
                            <i class="fas fa-save me-1"></i> Simpan Setoran Deposit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<!-- Library jsPDF untuk Ekspor Struk Rekap PDF Vektor Multi-Halaman (Anti-Pecah) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    function kasirNotify(icon, title, text) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: icon,
                title: title,
                text: text,
                confirmButtonColor: '#667eea'
            });
        }
        return Promise.resolve();
    }

    function formatRupiahVal(angka) {
        return new Intl.NumberFormat('id-ID').format(angka || 0);
    }

    // Data rekap kasbon untuk WhatsApp & PDF Vektor
    const kasbonData = {
        @if(isset($kasbonByCustomer) && $kasbonByCustomer->isNotEmpty())
        @foreach($kasbonByCustomer as $gKey => $trxs)
        @php
            $cTx = $trxs->first();
            $cLastTx = $trxs->last();
            $cName = $cTx->customer->name ?? 'Pelanggan Umum (Tanpa Nama)';
            $cPhone = $cTx->customer->phone ?? '';
            $cTotal = $trxs->sum('total_amount');
            $cOldest = $cTx ? $cTx->transaction_date->format('d/m/Y') : now()->format('d/m/Y');
            $cNewest = $cLastTx ? $cLastTx->transaction_date->format('d/m/Y') : now()->format('d/m/Y');
            $cRekapDate = now()->format('d/m/Y H:i');
        @endphp
        '{{ $gKey }}': {
            customerName: @json($cName),
            customerPhone: @json($cPhone),
            totalAmount: {{ $cTotal }},
            oldestDate: @json($cOldest),
            newestDate: @json($cNewest),
            rekapDate: @json($cRekapDate),
            items: [
                @foreach($trxs as $tx)
                {
                    id: '{{ str_pad($tx->id, 6, "0", STR_PAD_LEFT) }}',
                    date: '{{ $tx->transaction_date->format("d/m/Y H:i") }}',
                    branch: @json($tx->branch->name ?? ''),
                    cashier: @json($tx->createdBy->name ?? 'Kasir'),
                    driver: @json($tx->driver->name ?? ''),
                    amount: {{ $tx->total_amount }},
                    notes: @json($tx->notes ?? ''),
                    products: [
                        @if($tx->details)
                            @foreach($tx->details as $d)
                            {
                                name: @json($d->product->name ?? 'Produk'),
                                quantity: {{ $d->quantity }},
                                subtotal: {{ (int) round($d->total_price ?? ($d->quantity * $d->unit_price)) }}
                            },
                            @endforeach
                        @endif
                    ]
                },
                @endforeach
            ]
        },
        @endforeach
        @endif
    };

    /**
     * Sinkronisasi state terpadu per-modal Daftar Kasbon:
     * 1. Hitung ulang total dari DOM seluruh checkbox tercentang (berbasis data-amount mentah)
     * 2. Perbarui highlight baris, select-all (termasuk indeterminate), dan bar ringkasan
     * 3. Gabungkan aturan wajib bukti Non-Tunai + minimal 1 nota dipilih pada tombol submit
     */
    window.syncBulkModalState = function(modalEl) {
        if (!modalEl) return;

        const groupKey = modalEl.getAttribute('data-group-key') || '';
        const totalPending = parseFloat(modalEl.getAttribute('data-total-pending') || '0');
        const allItems = Array.from(modalEl.querySelectorAll('.item-checkbox'));
        const totalCount = parseInt(modalEl.getAttribute('data-total-count') || String(allItems.length), 10);

        let selectedCount = 0;
        let selectedTotal = 0;

        allItems.forEach(function(cb) {
            const row = cb.closest('.kd-kasbon-row');
            if (cb.checked) {
                selectedCount++;
                selectedTotal += parseFloat(cb.getAttribute('data-amount') || '0');
                if (row) row.classList.add('is-selected');
            } else {
                if (row) row.classList.remove('is-selected');
            }
        });

        const selectAllBtn = modalEl.querySelector('.select-all-btn');
        if (selectAllBtn) {
            const allChecked = (allItems.length > 0 && selectedCount === allItems.length);
            const someChecked = (selectedCount > 0 && selectedCount < allItems.length);
            selectAllBtn.checked = allChecked;
            selectAllBtn.indeterminate = someChecked;
        }

        const countEl = modalEl.querySelector('[data-kasbon-count]');
        const totalEl = modalEl.querySelector('[data-kasbon-selected-total]');
        const remEl = modalEl.querySelector('[data-kasbon-remaining]');
        const summaryBar = modalEl.querySelector('.kd-kasbon-summary-bar');

        if (countEl) {
            countEl.textContent = `${selectedCount} dari ${totalCount} nota dipilih`;
        }
        if (totalEl) {
            totalEl.textContent = 'Rp ' + formatRupiahVal(selectedTotal);
        }
        if (remEl) {
            if (totalCount > 0 && selectedCount === totalCount) {
                remEl.innerHTML = '<span class="kasir-badge kasir-badge-success"><i class="fas fa-check-circle"></i> Lunas semua</span>';
            } else {
                const remaining = Math.max(0, totalPending - selectedTotal);
                remEl.innerHTML = 'Sisa kasbon setelah pelunasan: <strong>Rp ' + formatRupiahVal(remaining) + '</strong>';
            }
        }
        if (summaryBar) {
            summaryBar.style.borderColor = selectedCount > 0 ? 'var(--kasir-primary)' : 'var(--kasir-brand-border)';
        }

        // Aturan metode bayar & wajib bukti Non-Tunai
        const methodSelect = modalEl.querySelector('.kd-kasbon-method-sel') || (groupKey ? document.getElementById('bulk_payment_method_' + groupKey) : null);
        const method = methodSelect ? methodSelect.value : 'cash';
        const selectDate = modalEl.querySelector('.kd-kasbon-date-sel') || (groupKey ? document.getElementById('bulk_settlement_date_mode_' + groupKey) : null);
        const optNota = groupKey ? document.getElementById('bulk_opt_nota_date_' + groupKey) : null;
        const warn = groupKey ? document.getElementById('bulk_warning_nota_' + groupKey) : null;
        const proofInput = modalEl.querySelector('.kd-kasbon-proof-inp') || (groupKey ? document.getElementById('bulk_payment_proof_' + groupKey) : null);
        const proofBadge = groupKey ? document.getElementById('bulk_proof_required_badge_' + groupKey) : null;
        const proofHelp = groupKey ? document.getElementById('bulk_proof_help_' + groupKey) : null;
        const proofAlert = groupKey ? document.getElementById('bulk_proof_alert_' + groupKey) : null;
        const methodNameSpan = groupKey ? document.getElementById('bulk_proof_method_name_' + groupKey) : null;
        const btnSubmit = modalEl.querySelector('.kd-kasbon-submit-btn') || (groupKey ? document.getElementById('bulk_btn_submit_' + groupKey) : null);

        const isCash = (method === 'cash');
        const hasFile = !!(proofInput && proofInput.files && proofInput.files.length > 0);

        if (optNota && selectDate) {
            if (isCash) {
                optNota.disabled = true;
                optNota.style.display = 'none';
                selectDate.value = 'today';
                if (warn) warn.classList.add('d-none');
            } else {
                optNota.disabled = false;
                optNota.style.display = '';
                if (warn) {
                    warn.classList.toggle('d-none', selectDate.value !== 'nota_date');
                }
            }
        }

        if (isCash) {
            if (proofInput) {
                proofInput.removeAttribute('required');
                proofInput.classList.remove('border-danger', 'border-2', 'border-success');
            }
            if (proofBadge) {
                proofBadge.innerHTML = '<span class="badge bg-light text-muted border">Opsional (Cash)</span>';
            }
            if (proofHelp) {
                proofHelp.innerHTML = '*Opsional jika bayar Cash. Cukup upload <strong>1 file bukti</strong> jika ada.';
            }
            if (proofAlert) {
                proofAlert.classList.add('d-none');
            }
        } else {
            const methodName = methodSelect ? methodSelect.options[methodSelect.selectedIndex].text : 'Non-Tunai';
            if (methodNameSpan) methodNameSpan.textContent = methodName;

            if (proofInput) {
                proofInput.setAttribute('required', 'required');
            }

            if (hasFile) {
                if (proofInput) {
                    proofInput.classList.remove('border-danger');
                    proofInput.classList.add('border-success', 'border-2');
                }
                if (proofBadge) {
                    proofBadge.innerHTML = '<span class="badge bg-success text-white"><i class="fas fa-check me-1"></i>File Bukti Terlampir</span>';
                }
                if (proofHelp) {
                    proofHelp.innerHTML = '<strong class="text-success"><i class="fas fa-check-circle me-1"></i>Bukti siap diunggah:</strong> ' + proofInput.files[0].name;
                }
                if (proofAlert) {
                    proofAlert.classList.add('d-none');
                }
            } else {
                if (proofInput) {
                    proofInput.classList.remove('border-success');
                    proofInput.classList.add('border-danger', 'border-2');
                }
                if (proofBadge) {
                    proofBadge.innerHTML = '<span class="badge bg-danger text-white"><i class="fas fa-exclamation-circle me-1"></i>WAJIB UPLOAD BUKTI NOTA</span>';
                }
                if (proofHelp) {
                    proofHelp.innerHTML = '<strong class="text-danger">*Wajib diunggah!</strong> Lampirkan screenshot bukti transfer pelanggan untuk membuka tombol pelunasan.';
                }
                if (proofAlert) {
                    proofAlert.classList.remove('d-none');
                }
            }
        }

        // Gabungkan 2 aturan penguncian tombol submit:
        // (1) minimal 1 nota dipilih AND (2) Cash atau bukti terlampir
        if (btnSubmit) {
            if (selectedCount === 0) {
                btnSubmit.disabled = true;
                btnSubmit.className = 'btn btn-secondary text-white fw-bold kd-kasbon-submit-btn opacity-75';
                btnSubmit.innerHTML = '<i class="fas fa-ban me-1"></i> Pilih nota yang akan dilunasi';
            } else if (!isCash && !hasFile) {
                btnSubmit.disabled = true;
                btnSubmit.className = 'btn btn-secondary text-white fw-bold kd-kasbon-submit-btn opacity-75';
                btnSubmit.innerHTML = '<i class="fas fa-lock me-1"></i> Tombol Terkunci (Wajib Upload Bukti)';
            } else {
                btnSubmit.disabled = false;
                btnSubmit.className = 'btn btn-success fw-bold kd-kasbon-submit-btn';
                btnSubmit.innerHTML = `<i class="fas fa-check-double me-1"></i> Lunasi ${selectedCount} Nota &middot; Rp ${formatRupiahVal(selectedTotal)}`;
            }
        }
    };

    window.checkBulkSettlementMode = function(groupKey) {
        const modalEl = document.getElementById('modalGroup_' + groupKey);
        if (modalEl) syncBulkModalState(modalEl);
    };

    function updateBulkSelectedSummary(groupKey) {
        const modalEl = document.getElementById('modalGroup_' + groupKey);
        if (modalEl) syncBulkModalState(modalEl);
    }

    /**
     * Buat PDF Vektor A4 Multi-Halaman untuk Rekap Tagihan Kasbon (Tajam 100%, Anti-Pecah di WhatsApp)
     */
    function buildKasbonRekapPDF(groupKey, customerName) {
        const data = kasbonData[groupKey];
        if (!data || !window.jspdf || !window.jspdf.jsPDF) {
            return null;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });

        const pageW = doc.internal.pageSize.getWidth();
        const pageH = doc.internal.pageSize.getHeight();
        const margin = 15;
        const contentW = pageW - (margin * 2);
        const bottomLimit = pageH - 18;
        let y = 16;

        function ensureSpace(neededMm) {
            if (y + neededMm > bottomLimit) {
                doc.addPage();
                y = 16;
                // Header ringkas halaman lanjutan
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9);
                doc.setTextColor(90, 90, 90);
                doc.text(`ADIN CRYSTAL - Rekap Kasbon: ${data.customerName} (Lanjutan)`, margin, y);
                y += 4;
                doc.setDrawColor(200, 200, 200);
                doc.line(margin, y, pageW - margin, y);
                y += 6;
            }
        }

        // 1. Header Toko
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(16);
        doc.setTextColor(15, 23, 42);
        doc.text('ADIN CRYSTAL', pageW / 2, y, { align: 'center' });
        y += 5;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        doc.setTextColor(71, 85, 105);
        doc.text('Es Kristal Premium  |  Telp: 087811192774', pageW / 2, y, { align: 'center' });
        y += 5;

        // Banner Judul
        doc.setFillColor(220, 53, 69);
        doc.roundedRect(margin, y, contentW, 8, 1.5, 1.5, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10.5);
        doc.setTextColor(255, 255, 255);
        doc.text('STRUK REKAP TAGIHAN KASBON', pageW / 2, y + 5.5, { align: 'center' });
        y += 13;

        // 2. Info Pelanggan
        const metaRows = [
            ['Pelanggan', data.customerName],
            data.customerPhone ? ['No. WhatsApp', data.customerPhone] : null,
            ['Tgl Rekap', data.rekapDate],
            ['Total Struk', `${data.items.length} Transaksi Belum Lunas`],
            ['Periode', `${data.oldestDate} s/d ${data.newestDate}`],
        ].filter(Boolean);

        doc.setFontSize(9.5);
        metaRows.forEach(function(row) {
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(51, 65, 85);
            doc.text(row[0], margin, y);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(15, 23, 42);
            doc.text(': ' + row[1], margin + 32, y);
            y += 5;
        });

        y += 2;
        doc.setDrawColor(30, 41, 59);
        doc.setLineWidth(0.4);
        doc.line(margin, y, pageW - margin, y);
        y += 6;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.setTextColor(15, 23, 42);
        doc.text('RINCIAN TRANSAKSI TERTUNDA', pageW / 2, y, { align: 'center' });
        y += 6;

        // 3. Daftar Transaksi & Item Produk Lengkap
        data.items.forEach(function(item, idx) {
            const products = Array.isArray(item.products) ? item.products : [];
            const noteLines = item.notes ? doc.splitTextToSize('Ket: ' + item.notes, contentW - 8) : [];
            const approxHeight = 16 + (products.length * 4.8) + (noteLines.length * 4.2) + 6;
            ensureSpace(Math.min(approxHeight, 60));

            // Judul Struk + Tanggal
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9.5);
            doc.setTextColor(15, 23, 42);
            doc.text(`${idx + 1}. Struk #${item.id}`, margin, y);
            doc.text(item.date || '', pageW - margin, y, { align: 'right' });
            y += 4.5;

            // Meta Cabang / Kasir / Driver
            const metaParts = [];
            if (item.branch) metaParts.push('Cabang: ' + item.branch);
            if (item.cashier) metaParts.push('Kasir: ' + item.cashier);
            if (item.driver) metaParts.push('Driver: ' + item.driver);
            if (metaParts.length > 0) {
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8.2);
                doc.setTextColor(100, 116, 139);
                const metaTextLines = doc.splitTextToSize(metaParts.join(' | '), contentW - 6);
                metaTextLines.forEach(function(line) {
                    ensureSpace(5);
                    doc.text(line, margin + 4, y);
                    y += 4;
                });
            }

            // Rincian Produk per Struk
            products.forEach(function(prod) {
                const label = `- ${prod.name} (x${prod.quantity})`;
                const subStr = 'Rp ' + formatRupiahVal(prod.subtotal || 0);
                const wrappedProd = doc.splitTextToSize(label, contentW - 42);
                ensureSpace(wrappedProd.length * 4.5 + 2);

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(9);
                doc.setTextColor(51, 65, 85);
                wrappedProd.forEach(function(pLine, pIdx) {
                    doc.text(pLine, margin + 4, y);
                    if (pIdx === 0) {
                        doc.text(subStr, pageW - margin, y, { align: 'right' });
                    }
                    y += 4.5;
                });
            });

            // Catatan Transaksi
            if (noteLines.length > 0) {
                doc.setFont('helvetica', 'italic');
                doc.setFontSize(8);
                doc.setTextColor(100, 116, 139);
                noteLines.forEach(function(nLine) {
                    ensureSpace(4.5);
                    doc.text(nLine, margin + 4, y);
                    y += 4;
                });
            }

            // Subtotal Transaksi
            ensureSpace(7);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(9);
            doc.setTextColor(220, 53, 69);
            doc.text('Subtotal:', margin + 4, y);
            doc.text('Rp ' + formatRupiahVal(item.amount), pageW - margin, y, { align: 'right' });
            y += 4;

            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(0.2);
            doc.line(margin, y, pageW - margin, y);
            y += 4.5;
        });

        // 4. Total Keseluruhan + Status + Footer Penutup
        ensureSpace(34);
        y += 1;
        doc.setDrawColor(15, 23, 42);
        doc.setLineWidth(0.6);
        doc.line(margin, y, pageW - margin, y);
        y += 7;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(12);
        doc.setTextColor(15, 23, 42);
        doc.text('TOTAL TAGIHAN:', margin, y);
        doc.setFontSize(13.5);
        doc.setTextColor(220, 53, 69);
        doc.text('Rp ' + formatRupiahVal(data.totalAmount), pageW - margin, y, { align: 'right' });
        y += 6;

        doc.setDrawColor(220, 53, 69);
        doc.setLineWidth(0.4);
        doc.rect(margin, y, contentW, 8);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9.5);
        doc.setTextColor(220, 53, 69);
        doc.text('STATUS: BELUM LUNAS (KASBON)', pageW / 2, y + 5.5, { align: 'center' });
        y += 13;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(71, 85, 105);
        doc.text('Mohon kesediaannya untuk melakukan pembayaran melalui Tunai / Transfer. Terima kasih atas kerjasamanya!', pageW / 2, y, {
            align: 'center',
            maxWidth: contentW
        });

        // Nomor halaman di setiap halaman
        const totalPages = doc.getNumberOfPages();
        for (let p = 1; p <= totalPages; p++) {
            doc.setPage(p);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(148, 163, 184);
            doc.text(`Halaman ${p} / ${totalPages}`, pageW / 2, pageH - 8, { align: 'center' });
        }

        const cleanName = (customerName || data.customerName || 'Pelanggan').replace(/[^a-zA-Z0-9]/g, '_');
        const dateTag = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        const fileName = `Rekap_Tagihan_${cleanName}_${dateTag}.pdf`;
        const blob = doc.output('blob');

        return { blob, fileName };
    }

    /**
     * Bagikan Struk Rekap Tagihan Customer sebagai PDF Vektor (WhatsApp / Share Sheet)
     */
    window.shareStrukRekapPDF = function(groupKey, customerName, defaultPhone) {
        const btn = document.getElementById('btnShareStruk_' + groupKey);
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyiapkan PDF...';
            btn.disabled = true;
        }

        let phone = defaultPhone || '';
        if (!phone && kasbonData[groupKey] && kasbonData[groupKey].customerPhone) {
            phone = kasbonData[groupKey].customerPhone;
        }
        if (phone) {
            phone = phone.replace(/[^0-9]/g, '');
            if (phone.startsWith('0')) {
                phone = '62' + phone.substring(1);
            } else if (phone.startsWith('8')) {
                phone = '62' + phone;
            }
        }

        try {
            const pdfResult = buildKasbonRekapPDF(groupKey, customerName);
            if (!pdfResult) {
                kasirNotify('error', 'Library PDF Belum Siap', 'Pastikan koneksi internet aktif lalu muat ulang halaman, atau gunakan "Kirim Chat Teks WA".');
                if (btn) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
                return;
            }

            const { blob, fileName } = pdfResult;
            const file = new File([blob], fileName, { type: 'application/pdf' });
            const shareTitle = 'Rekap Tagihan Kasbon - ' + customerName;
            const shareText = `Halo kak ${customerName}, berikut kami kirimkan file PDF rincian rekap struk tagihan belanja Anda di Adin Crystal yang belum terlunasi. Mohon kesediaannya untuk melakukan pelunasan. Terima kasih banyak.`;

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                navigator.share({
                    files: [file],
                    title: shareTitle,
                    text: shareText
                }).finally(() => {
                    if (btn) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                });
            } else {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                let waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`;
                if (phone) {
                    waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(shareText)}`;
                }

                kasirNotify(
                    'success',
                    'PDF Struk Berhasil Diunduh',
                    `File ${fileName} telah diunduh. Anda akan dialihkan ke WhatsApp untuk melampirkan dan mengirimkannya ke ${customerName}.`
                ).then(() => {
                    window.open(waUrl, '_blank');
                });

                if (btn) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }
        } catch (err) {
            console.error('Error saat membuat PDF struk:', err);
            kasirNotify('error', 'Gagal Membuat PDF Struk', 'Silakan gunakan tombol "Kirim Chat Teks WA".');
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    };

    window.shareStrukRekapImage = window.shareStrukRekapPDF;

    /**
     * Cetak Struk Rekap Thermal ke Printer
     */
    window.printStrukRekap = function(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;

        const printWindow = window.open('', '_blank', 'width=450,height=650');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Cetak Struk Rekap Kasbon</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @page { size: 80mm auto; margin: 2mm; }
                    body {
                        margin: 0;
                        padding: 6px;
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 9.5pt;
                        color: #000;
                        background: #fff;
                    }
                    .rekap-receipt-card {
                        width: 100% !important;
                        max-width: 80mm !important;
                        border: none !important;
                        box-shadow: none !important;
                        padding: 0 !important;
                    }
                </style>
            </head>
            <body>
                ${el.outerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    };

    /**
     * Kirim Teks Rincian Tagihan ke WhatsApp
     */
    window.sendChatWAText = function(groupKey, customerName, defaultPhone, totalAmount) {
        shareCustomerKasbonWA(groupKey);
    };

    function openCustomerKasbonWA(data, rawPhone) {
        let phone = (rawPhone || '').replace(/[^0-9]/g, '');
        if (!phone) return;
        if (phone.startsWith('0')) {
            phone = '62' + phone.substring(1);
        } else if (phone.startsWith('8')) {
            phone = '62' + phone;
        }

        let msg = `*TAGIHAN KASBON - ADIN CRYSTAL*\n`;
        msg += `Yth. *${data.customerName}*,\n\n`;
        msg += `Berikut rincian tagihan pesanan Anda yang belum terselesaikan:\n`;
        msg += `----------------------------------------\n`;

        data.items.forEach((item, idx) => {
            msg += `${idx + 1}. *Struk #${item.id}* (${item.date})\n`;
            if (item.branch) {
                msg += `   Cabang: ${item.branch}\n`;
            }
            if (item.cashier) {
                msg += `   Kasir: ${item.cashier}\n`;
            }
            if (item.products && item.products.length > 0) {
                const prodList = item.products.map(p => `${p.name} (${p.quantity})`).join(', ');
                msg += `   Item: ${prodList}\n`;
            }
            if (item.notes) {
                msg += `   Ket: ${item.notes}\n`;
            }
            msg += `   Nominal: *Rp ${formatRupiahVal(item.amount)}*\n\n`;
        });

        msg += `----------------------------------------\n`;
        msg += `*TOTAL TAGIHAN: Rp ${formatRupiahVal(data.totalAmount)}*\n\n`;
        msg += `Mohon kesediaannya untuk segera melakukan pelunasan via Tunai / Transfer.\n`;
        msg += `Terima kasih atas perhatian dan kerjasamanya.`;

        const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(msg)}`;

        if (navigator.share && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            navigator.share({
                title: 'Tagihan Kasbon ' + data.customerName,
                text: msg
            }).catch(() => {
                window.open(waUrl, '_blank');
            });
        } else {
            window.open(waUrl, '_blank');
        }
    }

    window.shareCustomerKasbonWA = function(groupKey) {
        const data = kasbonData[groupKey];
        if (!data) return;

        if (data.customerPhone) {
            openCustomerKasbonWA(data, data.customerPhone);
            return;
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Nomor WhatsApp Pelanggan',
                text: `Masukkan nomor WhatsApp untuk ${data.customerName} (contoh: 08123456789):`,
                input: 'tel',
                inputPlaceholder: '08123456789',
                showCancelButton: true,
                confirmButtonText: 'Lanjut ke WhatsApp',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#198754',
                inputValidator: (value) => {
                    if (!value || !value.replace(/[^0-9]/g, '')) {
                        return 'Masukkan nomor WhatsApp yang valid.';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    openCustomerKasbonWA(data, result.value);
                }
            });
        }
    };

    window.shareGlobalKasbonWA = function() {
        if (!kasbonData || Object.keys(kasbonData).length === 0) {
            kasirNotify('info', 'Tidak Ada Kasbon Aktif', 'Tidak ada data kasbon aktif untuk direkap.');
            return;
        }

        let msg = `*REKAP SELURUH PIUTANG KASBON*\n`;
        msg += `Tanggal: ${new Date().toLocaleDateString('id-ID')}\n`;
        msg += `Toko: *ADIN CRYSTAL*\n`;
        msg += `----------------------------------------\n\n`;

        let totalAll = 0;
        let count = 1;

        for (const key in kasbonData) {
            const item = kasbonData[key];
            totalAll += item.totalAmount;
            const phoneInfo = item.customerPhone ? ` (${item.customerPhone})` : '';
            msg += `${count}. *${item.customerName}*${phoneInfo}\n`;
            msg += `   - ${item.items.length} Struk tertunda\n`;
            msg += `   - Total: *Rp ${formatRupiahVal(item.totalAmount)}*\n\n`;
            count++;
        }

        msg += `----------------------------------------\n`;
        msg += `*GRAND TOTAL PIUTANG: Rp ${formatRupiahVal(totalAll)}*\n\n`;
        msg += `Mohon bantuan tim untuk follow up penagihan. Terima kasih.`;

        const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(msg)}`;

        if (navigator.share && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            navigator.share({
                title: 'Rekap Seluruh Kasbon Adin Crystal',
                text: msg
            }).catch(() => {
                window.open(waUrl, '_blank');
            });
        } else {
            window.open(waUrl, '_blank');
        }
    };

    // =============================================================
    // FITUR PENYELESAIAN PENGIRIMAN & DEPOSIT PELANGGAN (EVENT DELEGATION)
    // =============================================================
    let kdIsProgrammaticScroll = false;
    let kdPendingScrollTimer = null;
    let kdPendingScrollTarget = null;

    function isTouchCoarseDevice() {
        return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    }

    function selectAllInputSafely(inputEl) {
        if (!inputEl) return;
        try {
            if (typeof inputEl.setSelectionRange === 'function') {
                inputEl.setSelectionRange(0, inputEl.value.length);
            } else {
                inputEl.select();
            }
        } catch (_) {
            try { inputEl.select(); } catch (__) {}
        }
    }

    function updateKeyboardCompactState(explicitFocusedInput) {
        const openSettleModal = document.querySelector('.kd-settle-modal.show');
        const openKasbonModal = document.querySelector('.kasbon-bulk-modal.show');
        const vv = window.visualViewport;
        const heightDropped = vv ? ((window.innerHeight - vv.height) > 150) : false;
        const activeEl = explicitFocusedInput !== undefined ? explicitFocusedInput : document.activeElement;

        document.querySelectorAll('.kd-settle-modal').forEach(function(m) {
            if (m !== openSettleModal) {
                m.classList.remove('kd-settle-kbd');
                m.style.removeProperty('--kd-vvh');
                m.style.removeProperty('--kd-vvtop');
            }
        });

        document.querySelectorAll('.kasbon-bulk-modal').forEach(function(m) {
            if (m !== openKasbonModal) {
                m.classList.remove('kd-kasbon-kbd');
                m.style.removeProperty('--kd-vvh');
                m.style.removeProperty('--kd-vvtop');
            }
        });

        if (openSettleModal) {
            if (vv) {
                openSettleModal.style.setProperty('--kd-vvh', Math.round(vv.height) + 'px');
                openSettleModal.style.setProperty('--kd-vvtop', Math.max(0, Math.round(vv.offsetTop)) + 'px');
            }
            const isDepositFocused = !!(activeEl && activeEl.classList && activeEl.classList.contains('kd-settle-driver-input'));
            const compactSettle = heightDropped || (isTouchCoarseDevice() && isDepositFocused);
            openSettleModal.classList.toggle('kd-settle-kbd', compactSettle);
        }

        if (openKasbonModal) {
            if (vv) {
                openKasbonModal.style.setProperty('--kd-vvh', Math.round(vv.height) + 'px');
                openKasbonModal.style.setProperty('--kd-vvtop', Math.max(0, Math.round(vv.offsetTop)) + 'px');
            }
            const isKasbonNoteFocused = !!(activeEl && activeEl.classList && activeEl.classList.contains('kd-kasbon-note-inp'));
            const compactKasbon = heightDropped || (isTouchCoarseDevice() && isKasbonNoteFocused);
            openKasbonModal.classList.toggle('kd-kasbon-kbd', compactKasbon);
        }
    }

    function scrollDepositPanelIntoModalView(modalEl, panel) {
        if (!modalEl || !panel || panel.classList.contains('d-none')) return;
        const scrollBody = modalEl.querySelector('.kd-settle-body');
        if (!scrollBody) return;

        const isCompact = modalEl.classList.contains('kd-settle-kbd');
        const bodyRect = scrollBody.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const relativeTop = panelRect.top - bodyRect.top + scrollBody.scrollTop;

        if (isCompact || isTouchCoarseDevice()) {
            // Letakkan panel deposit di sekitar sepertiga atas area terlihat modal-body
            const desiredOffset = Math.max(8, Math.round(scrollBody.clientHeight * 0.12));
            const targetTop = Math.max(0, relativeTop - desiredOffset);
            kdIsProgrammaticScroll = true;
            scrollBody.scrollTo({ top: targetTop, behavior: 'smooth' });
            setTimeout(function() { kdIsProgrammaticScroll = false; }, 350);
        } else {
            // Desktop: hanya scroll ringan jika panel terpotong di bawah viewport modal-body
            const overflowBottom = panelRect.bottom - bodyRect.bottom;
            const overflowTop = bodyRect.top - panelRect.top;
            if (overflowBottom > 0) {
                kdIsProgrammaticScroll = true;
                scrollBody.scrollBy({ top: overflowBottom + 16, behavior: 'smooth' });
                setTimeout(function() { kdIsProgrammaticScroll = false; }, 350);
            } else if (overflowTop > 0) {
                kdIsProgrammaticScroll = true;
                scrollBody.scrollBy({ top: -(overflowTop + 16), behavior: 'smooth' });
                setTimeout(function() { kdIsProgrammaticScroll = false; }, 350);
            }
        }
    }

    function scheduleDepositScroll(modalEl, panel) {
        if (!modalEl || !panel) return;
        if (kdPendingScrollTimer) {
            clearTimeout(kdPendingScrollTimer);
            kdPendingScrollTimer = null;
        }

        if (!isTouchCoarseDevice()) {
            scrollDepositPanelIntoModalView(modalEl, panel);
            return;
        }

        kdPendingScrollTarget = { modalEl: modalEl, panel: panel };
        kdPendingScrollTimer = setTimeout(function() {
            if (kdPendingScrollTarget) {
                scrollDepositPanelIntoModalView(kdPendingScrollTarget.modalEl, kdPendingScrollTarget.panel);
                kdPendingScrollTarget = null;
            }
            kdPendingScrollTimer = null;
        }, 400);
    }

    if (window.visualViewport && !window.__kdSettleViewportBound) {
        window.__kdSettleViewportBound = true;
        const onViewportUpdate = function(evt) {
            if (kdIsProgrammaticScroll && evt && evt.type === 'scroll') return;
            updateKeyboardCompactState();
            if (evt && evt.type === 'resize' && kdPendingScrollTarget) {
                if (kdPendingScrollTimer) {
                    clearTimeout(kdPendingScrollTimer);
                    kdPendingScrollTimer = null;
                }
                const target = kdPendingScrollTarget;
                kdPendingScrollTarget = null;
                scrollDepositPanelIntoModalView(target.modalEl, target.panel);
            }
        };
        window.visualViewport.addEventListener('resize', onViewportUpdate);
        window.visualViewport.addEventListener('scroll', onViewportUpdate);
    }

    window.toggleTripDepositPanel = function(trxId) {
        const panel = document.getElementById('deposit_panel_' + trxId);
        const btn = document.getElementById('btn_dep_' + trxId);
        const card = document.getElementById('settle_card_' + trxId);
        const modalEl = panel ? panel.closest('.kd-settle-modal') : null;
        if (!panel) return;

        const willOpen = panel.classList.contains('d-none');

        if (willOpen) {
            // 1. Tampilkan panel secara instan -> paksa reflow -> fokus sinkron dalam handler klik (wajib di iPad Safari)
            panel.classList.remove('d-none');
            void panel.offsetHeight;

            if (card) card.classList.add('kd-settle-card--deposit-open');
            if (btn) {
                btn.setAttribute('aria-expanded', 'true');
                btn.classList.add('is-open');
                btn.innerHTML = '<i class="fas fa-times me-1"></i> Tutup form deposit';
            }

            const driverInput = document.getElementById('driver_money_' + trxId);
            if (driverInput) {
                driverInput.focus({ preventScroll: true });
                selectAllInputSafely(driverInput);
                updateKeyboardCompactState(driverInput);
            }
            scheduleDepositScroll(modalEl, panel);
        } else {
            const driverInput = document.getElementById('driver_money_' + trxId);
            const depositInput = document.getElementById('deposit_val_' + trxId);
            const summaryEl = document.getElementById('deposit_summary_' + trxId);

            if (driverInput) {
                driverInput.blur();
                driverInput.value = '';
            }
            if (depositInput) depositInput.value = 0;
            if (summaryEl) summaryEl.innerHTML = '';

            panel.classList.add('d-none');
            if (card) card.classList.remove('kd-settle-card--deposit-open');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
                btn.classList.remove('is-open');
                btn.innerHTML = '<i class="fas fa-piggy-bank me-1"></i> + Deposit Pelanggan';
            }

            updateKeyboardCompactState(null);
            if (modalEl) recalcTripModalSummary(modalEl);
        }
    };

    window.calcTripDeposit = function(trxId) {
        const driverInput = document.getElementById('driver_money_' + trxId);
        const depositInput = document.getElementById('deposit_val_' + trxId);
        const summaryEl = document.getElementById('deposit_summary_' + trxId);
        if (!driverInput || !depositInput) return;

        const payable = parseFloat(driverInput.getAttribute('data-payable') || '0');
        const saldoAwal = parseFloat(driverInput.getAttribute('data-deposit-bal') || '0');
        const rawTrimmed = driverInput.value.replace(/[^0-9]/g, '');
        const driverMoney = rawTrimmed ? parseFloat(rawTrimmed) : 0;

        if (!rawTrimmed) {
            depositInput.value = 0;
            if (summaryEl) summaryEl.innerHTML = '';
            return;
        }

        if (driverMoney >= payable) {
            const depositBaru = driverMoney - payable;
            const saldoAkhir = saldoAwal + depositBaru;
            const lunasOrder = payable;
            depositInput.value = depositBaru;

            if (summaryEl) {
                summaryEl.innerHTML = `
                    <div class="kd-settle-result-grid">
                        <div class="kd-settle-dep-box">
                            <span class="kd-settle-dep-label">Lunas Untuk Order Ini</span>
                            <div class="kd-settle-dep-val text-success kasir-num">Rp ${formatRupiahVal(lunasOrder)}</div>
                        </div>
                        <div class="kd-settle-dep-box">
                            <span class="kd-settle-dep-label">Lebih Bayar, Masuk Deposit</span>
                            <div class="kd-settle-dep-val kasir-num" style="color: #0f766e;">+Rp ${formatRupiahVal(depositBaru)}</div>
                        </div>
                        <div class="kd-settle-dep-box">
                            <span class="kd-settle-dep-label">Saldo Deposit Setelah Ini</span>
                            <div class="kd-settle-dep-val text-dark kasir-num">Rp ${formatRupiahVal(saldoAkhir)}</div>
                        </div>
                    </div>
                `;
            }
        } else {
            depositInput.value = 0;
            const kurang = payable - driverMoney;
            if (summaryEl) {
                summaryEl.innerHTML = `
                    <div class="alert alert-warning alert-permanent py-2 px-3 mb-0 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Uang diterima <strong>kurang Rp ${formatRupiahVal(kurang)}</strong> dari tagihan (Rp ${formatRupiahVal(payable)}). Tidak ada kelebihan untuk disimpan sebagai deposit.</span>
                    </div>
                `;
            }
        }
    };

    window.toggleTripDepositBtn = function(trxId) {
        const paySel = document.getElementById('pay_sel_' + trxId);
        const btnDep = document.getElementById('btn_dep_' + trxId);
        const panel = document.getElementById('deposit_panel_' + trxId);
        const card = document.getElementById('settle_card_' + trxId);
        const kasbonAlert = document.getElementById('kasbon_alert_' + trxId);
        const dateWrap = document.getElementById('date_wrap_' + trxId);
        if (!paySel) return;

        const isKasbon = (paySel.value === 'kasbon');

        if (kasbonAlert) kasbonAlert.classList.toggle('d-none', !isKasbon);
        if (dateWrap) dateWrap.classList.toggle('d-none', isKasbon);

        if (isKasbon) {
            const driverInput = document.getElementById('driver_money_' + trxId);
            const depositInput = document.getElementById('deposit_val_' + trxId);
            const summaryEl = document.getElementById('deposit_summary_' + trxId);
            if (driverInput) {
                driverInput.blur();
                driverInput.value = '';
            }
            if (depositInput) depositInput.value = 0;
            if (summaryEl) summaryEl.innerHTML = '';

            if (btnDep) {
                btnDep.classList.add('d-none');
                btnDep.classList.remove('is-open');
                btnDep.setAttribute('aria-expanded', 'false');
                btnDep.innerHTML = '<i class="fas fa-piggy-bank me-1"></i> + Deposit Pelanggan';
            }
            if (panel) panel.classList.add('d-none');
            if (card) card.classList.remove('kd-settle-card--deposit-open');
            updateKeyboardCompactState(null);
        } else {
            if (btnDep) btnDep.classList.remove('d-none');
        }
    };

    function syncCardMethodButtons(trxId) {
        const paySel = document.getElementById('pay_sel_' + trxId);
        const card = document.getElementById('settle_card_' + trxId);
        const unselectedMsg = document.getElementById('unselected_msg_' + trxId);
        if (!paySel || !card) return;

        const val = paySel.value;
        card.querySelectorAll('.kd-settle-seg-btn').forEach(function(btn) {
            const match = (btn.getAttribute('data-pay-value') === val);
            btn.classList.toggle('is-active', match);
            btn.setAttribute('aria-pressed', match ? 'true' : 'false');
        });

        if (val) {
            card.classList.remove('kd-settle-card--unselected');
            if (unselectedMsg) unselectedMsg.classList.add('d-none');
        }
    }

    function syncCardDateButtons(trxId) {
        const dateSel = document.getElementById('date_mode_sel_' + trxId);
        const card = document.getElementById('settle_card_' + trxId);
        if (!dateSel || !card) return;

        const val = dateSel.value || 'today';
        card.querySelectorAll('.kd-settle-date-btn').forEach(function(btn) {
            const match = (btn.getAttribute('data-date-value') === val);
            btn.classList.toggle('is-active', match);
            btn.setAttribute('aria-pressed', match ? 'true' : 'false');
        });
    }

    window.recalcTripModalSummary = function(modalEl) {
        if (!modalEl) return;

        let cashTotal = 0;
        let kasbonTotal = 0;
        let depositTotal = 0;
        const nonCashTotals = {};

        modalEl.querySelectorAll('.kd-settle-card').forEach(function(card) {
            const trxId = card.getAttribute('data-trx-id');
            const payable = parseFloat(card.getAttribute('data-payable') || '0');
            const isFullDeposit = card.getAttribute('data-full-deposit') === '1';

            const depInput = document.getElementById('deposit_val_' + trxId);
            const depAmt = depInput ? parseFloat(depInput.value || '0') : 0;
            depositTotal += depAmt;

            if (isFullDeposit) return;

            const paySel = document.getElementById('pay_sel_' + trxId);
            if (!paySel || !paySel.value) return;

            const slug = paySel.value.toLowerCase();
            if (slug === 'kasbon') {
                kasbonTotal += payable;
            } else if (slug === 'cash') {
                cashTotal += payable;
            } else {
                const selectedOpt = paySel.options[paySel.selectedIndex];
                const label = selectedOpt ? (selectedOpt.getAttribute('data-label') || selectedOpt.text) : slug.toUpperCase();
                if (!nonCashTotals[slug]) {
                    nonCashTotals[slug] = { label: label, total: 0 };
                }
                nonCashTotals[slug].total += payable;
            }
        });

        const elCash = modalEl.querySelector('[data-sum-cash]');
        const elNonCashWrap = modalEl.querySelector('[data-sum-noncash-wrap]');
        const elKasbon = modalEl.querySelector('[data-sum-kasbon]');
        const elDepWrap = modalEl.querySelector('[data-sum-deposit-wrap]');
        const elDep = modalEl.querySelector('[data-sum-deposit]');

        if (elCash) elCash.textContent = 'Rp ' + formatRupiahVal(cashTotal);
        if (elKasbon) elKasbon.textContent = 'Rp ' + formatRupiahVal(kasbonTotal);

        if (elNonCashWrap) {
            let html = '';
            Object.keys(nonCashTotals).forEach(function(key) {
                const item = nonCashTotals[key];
                if (item.total > 0) {
                    html += `
                        <div class="kd-settle-sum-item">
                            <span class="kd-settle-sum-label">Masuk ${item.label}</span>
                            <span class="kd-settle-sum-val kasir-num" style="color: var(--kasir-secondary);">Rp ${formatRupiahVal(item.total)}</span>
                        </div>
                    `;
                }
            });
            elNonCashWrap.innerHTML = html;
        }

        if (elDepWrap && elDep) {
            elDepWrap.classList.toggle('d-none', depositTotal <= 0);
            elDep.textContent = '+Rp ' + formatRupiahVal(depositTotal);
        }
    };

    // =============================================================
    // EVENT DELEGATION TUNGGAL: MODAL TRIP & KASBON & SEARCH
    // =============================================================
    document.addEventListener('shown.bs.modal', function(e) {
        const modalEl = e.target;
        if (modalEl.classList.contains('kd-settle-modal')) {
            modalEl.querySelectorAll('.kd-settle-card').forEach(function(card) {
                const trxId = card.getAttribute('data-trx-id');
                if (trxId) {
                    syncCardMethodButtons(trxId);
                    syncCardDateButtons(trxId);
                    toggleTripDepositBtn(trxId);
                }
            });
            recalcTripModalSummary(modalEl);
            updateKeyboardCompactState(null);
        } else if (modalEl.classList.contains('kasbon-bulk-modal')) {
            syncBulkModalState(modalEl);
            updateKeyboardCompactState(null);
        }
    });

    document.addEventListener('hidden.bs.modal', function(e) {
        const modalEl = e.target;
        if (modalEl.classList.contains('kd-settle-modal')) {
            if (kdPendingScrollTimer) {
                clearTimeout(kdPendingScrollTimer);
                kdPendingScrollTimer = null;
            }
            kdPendingScrollTarget = null;
            updateKeyboardCompactState(null);
        } else if (modalEl.classList.contains('kasbon-bulk-modal')) {
            updateKeyboardCompactState(null);
        }
    });

    // Cegah tombol isi cepat mengambil fokus agar keyboard tablet tidak turun saat diketuk
    const preventFocusLossForQuickBtn = function(e) {
        if (e.target.closest('.kd-settle-quick-btn')) {
            e.preventDefault();
        }
    };
    document.addEventListener('pointerdown', preventFocusLossForQuickBtn);
    document.addEventListener('mousedown', preventFocusLossForQuickBtn);

    document.addEventListener('focusin', function(e) {
        if (e.target.classList && e.target.classList.contains('kd-settle-driver-input')) {
            if (e.target.value === '0') {
                selectAllInputSafely(e.target);
            }
            updateKeyboardCompactState(e.target);
            const panel = e.target.closest('.kd-settle-deposit-panel');
            const modalEl = e.target.closest('.kd-settle-modal');
            scheduleDepositScroll(modalEl, panel);
        } else if (e.target.classList && e.target.classList.contains('kd-kasbon-note-inp')) {
            updateKeyboardCompactState(e.target);
        }
    });

    document.addEventListener('focusout', function(e) {
        if (e.target.classList && (e.target.classList.contains('kd-settle-driver-input') || e.target.classList.contains('kd-kasbon-note-inp'))) {
            setTimeout(function() {
                updateKeyboardCompactState();
            }, 60);
        }
    });

    document.addEventListener('click', function(e) {
        // 1. Segmen Metode Pembayaran
        const segBtn = e.target.closest('.kd-settle-seg-btn');
        if (segBtn) {
            const trxId = segBtn.getAttribute('data-trx-id');
            const val = segBtn.getAttribute('data-pay-value');
            const paySel = document.getElementById('pay_sel_' + trxId);
            if (paySel && val) {
                paySel.value = val;
                paySel.dispatchEvent(new Event('change', { bubbles: true }));
            }
            return;
        }

        // 2. Segmen Tanggal Pembukuan (Dropdown Kedua)
        const dateBtn = e.target.closest('.kd-settle-date-btn');
        if (dateBtn) {
            const trxId = dateBtn.getAttribute('data-trx-id');
            const val = dateBtn.getAttribute('data-date-value');
            const dateSel = document.getElementById('date_mode_sel_' + trxId);
            if (dateSel && val) {
                dateSel.value = val;
                syncCardDateButtons(trxId);
            }
            return;
        }

        // 3. Toggle Catatan
        const noteBtn = e.target.closest('.kd-settle-note-toggle');
        if (noteBtn) {
            const trxId = noteBtn.getAttribute('data-trx-id');
            const wrap = document.getElementById('note_wrap_' + trxId);
            const inp = document.getElementById('note_inp_' + trxId);
            if (wrap) {
                wrap.classList.toggle('d-none');
                const open = !wrap.classList.contains('d-none');
                noteBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && inp) inp.focus();
            }
            return;
        }

        // 4. Toggle Panel Deposit Pelanggan (satu-satunya mekanisme, fokus sinkron)
        const depBtn = e.target.closest('.kd-settle-deposit-btn');
        if (depBtn) {
            const trxId = depBtn.getAttribute('data-trx-id');
            if (trxId) toggleTripDepositPanel(trxId);
            return;
        }

        // 5. Tombol "Selesai" di panel deposit (turunkan keyboard)
        const doneBtn = e.target.closest('.kd-settle-dep-done-btn');
        if (doneBtn) {
            const trxId = doneBtn.getAttribute('data-trx-id');
            const driverInput = document.getElementById('driver_money_' + trxId);
            if (driverInput) driverInput.blur();
            updateKeyboardCompactState(null);
            return;
        }

        // 6. Tombol Cepat Nominal Deposit
        const quickBtn = e.target.closest('.kd-settle-quick-btn');
        if (quickBtn) {
            const trxId = quickBtn.getAttribute('data-trx-id');
            const amt = parseFloat(quickBtn.getAttribute('data-amount') || '0');
            const driverInput = document.getElementById('driver_money_' + trxId);
            if (driverInput) {
                driverInput.value = amt > 0 ? formatRupiahVal(amt) : '0';
                driverInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            return;
        }

        // 7. Tap seluruh baris pada modal Daftar Kasbon (area sentuh luas di tablet)
        const kasbonRow = e.target.closest('.kd-kasbon-row');
        if (kasbonRow) {
            if (e.target.closest('input, a, button, label, select, textarea')) return;
            const cb = kasbonRow.querySelector('.item-checkbox');
            const modalEl = kasbonRow.closest('.kasbon-bulk-modal');
            if (cb && modalEl) {
                cb.checked = !cb.checked;
                syncBulkModalState(modalEl);
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.target.classList && e.target.classList.contains('kd-settle-driver-input') && e.key === 'Enter') {
            e.preventDefault();
            e.target.blur();
            updateKeyboardCompactState(null);
            return;
        }

        const segBtn = e.target.closest('.kd-settle-seg-btn');
        if (!segBtn) return;
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;

        const group = segBtn.closest('.kd-settle-segments');
        if (!group) return;
        const btns = Array.from(group.querySelectorAll('.kd-settle-seg-btn'));
        const idx = btns.indexOf(segBtn);
        if (idx === -1) return;

        e.preventDefault();
        const nextIdx = e.key === 'ArrowRight'
            ? (idx + 1) % btns.length
            : (idx - 1 + btns.length) % btns.length;
        btns[nextIdx].focus();
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('kd-settle-pay-select')) {
            const trxId = e.target.getAttribute('data-trx-id');
            if (trxId) {
                syncCardMethodButtons(trxId);
                toggleTripDepositBtn(trxId);
                recalcTripModalSummary(e.target.closest('.kd-settle-modal'));
            }
            return;
        }

        const kasbonModal = e.target.closest('.kasbon-bulk-modal');
        if (!kasbonModal) return;

        if (e.target.classList.contains('select-all-btn')) {
            const checkedState = e.target.checked;
            kasbonModal.querySelectorAll('.item-checkbox').forEach(function(cb) {
                cb.checked = checkedState;
            });
            syncBulkModalState(kasbonModal);
        } else if (
            e.target.classList.contains('item-checkbox') ||
            e.target.classList.contains('kd-kasbon-method-sel') ||
            e.target.classList.contains('kd-kasbon-date-sel') ||
            e.target.classList.contains('kd-kasbon-proof-inp')
        ) {
            syncBulkModalState(kasbonModal);
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('kd-settle-driver-input')) {
            const inputEl = e.target;
            const trxId = inputEl.getAttribute('data-trx-id');

            // Format ribuan dengan titik sambil mempertahankan posisi kursor
            const rawVal = inputEl.value;
            const selPos = inputEl.selectionStart !== null ? inputEl.selectionStart : rawVal.length;
            const digitsBeforeCursor = rawVal.slice(0, selPos).replace(/[^0-9]/g, '').length;
            const cleanDigits = rawVal.replace(/[^0-9]/g, '');
            const formatted = cleanDigits ? formatRupiahVal(parseFloat(cleanDigits)) : '';

            if (rawVal !== formatted) {
                inputEl.value = formatted;
                if (document.activeElement === inputEl && typeof inputEl.setSelectionRange === 'function') {
                    let newPos = 0;
                    let seenDigits = 0;
                    while (newPos < formatted.length && seenDigits < digitsBeforeCursor) {
                        if (/[0-9]/.test(formatted.charAt(newPos))) {
                            seenDigits++;
                        }
                        newPos++;
                    }
                    try { inputEl.setSelectionRange(newPos, newPos); } catch (_) {}
                }
            }

            if (trxId) {
                calcTripDeposit(trxId);
                recalcTripModalSummary(inputEl.closest('.kd-settle-modal'));
            }
        }
    });

    // Fase capture (true) agar validasi berjalan SEBELUM listener global di app.blade.php
    document.addEventListener('submit', function(e) {
        const settleForm = e.target.closest('.kd-settle-form');
        if (settleForm) {
            if (settleForm.dataset.kdSubmitting === '1') {
                e.preventDefault();
                return false;
            }

            let firstInvalidCard = null;
            settleForm.querySelectorAll('.kd-settle-pay-select').forEach(function(sel) {
                const trxId = sel.getAttribute('data-trx-id');
                const card = document.getElementById('settle_card_' + trxId);
                const msg = document.getElementById('unselected_msg_' + trxId);
                if (!sel.value) {
                    if (card) card.classList.add('kd-settle-card--unselected');
                    if (msg) msg.classList.remove('d-none');
                    if (!firstInvalidCard) firstInvalidCard = card;
                }
            });

            if (firstInvalidCard) {
                e.preventDefault();
                firstInvalidCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                kasirNotify(
                    'warning',
                    'Metode Pembayaran Belum Dipilih',
                    'Silakan pilih metode pembayaran untuk seluruh pesanan sebelum menyimpan.'
                );
                return false;
            }

            settleForm.dataset.kdSubmitting = '1';
            const submitBtn = settleForm.querySelector('.kd-settle-submit-btn');
            if (submitBtn) {
                const originalHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';
                setTimeout(function() {
                    delete settleForm.dataset.kdSubmitting;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }, 10000);
            }
            return;
        }

        const bulkForm = e.target.closest('.kasbon-bulk-form');
        if (bulkForm) {
            if (bulkForm.dataset.kdSubmitting === '1') {
                e.preventDefault();
                return false;
            }

            const modalEl = bulkForm.closest('.kasbon-bulk-modal');
            const methodSelect = modalEl ? modalEl.querySelector('.kd-kasbon-method-sel') : null;
            const method = methodSelect ? methodSelect.value : 'cash';
            const proofInput = modalEl ? modalEl.querySelector('.kd-kasbon-proof-inp') : null;
            const checkedBoxes = bulkForm.querySelectorAll('input[name="transaction_ids[]"]:checked');

            if (checkedBoxes.length === 0) {
                e.preventDefault();
                kasirNotify(
                    'warning',
                    'Belum Ada Nota Dipilih',
                    'Silakan centang minimal 1 nota kasbon yang ingin dilunasi.'
                );
                return false;
            }

            if (method !== 'cash' && (!proofInput || !proofInput.files || proofInput.files.length === 0)) {
                e.preventDefault();
                kasirNotify(
                    'warning',
                    'Bukti Transfer Wajib Diunggah',
                    `Pembayaran Non-Tunai (${method.toUpperCase()}) wajib melampirkan foto atau screenshot bukti transfer WhatsApp.`
                ).then(() => {
                    if (proofInput) {
                        proofInput.focus();
                        proofInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
                return false;
            }

            bulkForm.dataset.kdSubmitting = '1';
            const btnSubmit = bulkForm.querySelector('.kd-kasbon-submit-btn');
            if (btnSubmit) {
                const originalHtml = btnSubmit.innerHTML;
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memproses...';
                setTimeout(function() {
                    delete bulkForm.dataset.kdSubmitting;
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                }, 10000);
            }
        }
    }, true);

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('kasbonCustomerSearch');
        if (searchInput) {
            const rows = document.querySelectorAll('.kasbon-customer-row');
            const emptyRow = document.getElementById('kasbonEmptySearchRow');
            searchInput.addEventListener('input', function() {
                const q = this.value.trim().toLowerCase();
                let visibleCount = 0;
                rows.forEach(function(row) {
                    const name = (row.getAttribute('data-customer-name') || '').toLowerCase();
                    const phone = (row.getAttribute('data-customer-phone') || '').toLowerCase();
                    const match = !q || name.includes(q) || phone.includes(q);
                    row.classList.toggle('d-none', !match);
                    if (match) visibleCount++;
                });
                if (emptyRow) {
                    emptyRow.classList.toggle('d-none', visibleCount > 0);
                }
            });
        }
    });
</script>
@endpush