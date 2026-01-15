<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class ExpensesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $expenses;

    public function __construct($expenses)
    {
        $this->expenses = $expenses;
    }

    public function collection()
    {
        return $this->expenses;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kategori',
            'Deskripsi',
            'Jumlah',
            'Dicatat oleh',
        ];
    }

    public function map($expense): array
    {
        // Pastikan relasi 'category' dan 'createdBy' sudah di-load
        return [
            Carbon::parse($expense->date)->isoFormat('D MMM YYYY'),
            $expense->category->name ?? 'N/A',
            $expense->description,
            $expense->amount,
            $expense->createdBy->name ?? 'Sistem',
        ];
    }
}