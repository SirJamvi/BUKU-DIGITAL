<?php

namespace App\Traits;

use App\Services\ExportService;

trait Exportable
{
    public function export($format = 'pdf', $data = null)
    {
        $exportService = new ExportService();
        
        if (is_null($data)) {
            $data = $this->all();
        }

        return $exportService->export($data, $this->getExportableView(), $format);
    }

    protected function getExportableView()
    {
        // Default view, bisa di-override di setiap model
        return 'exports.default'; 
    }
}