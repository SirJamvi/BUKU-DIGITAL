<?php
// 1. BusinessIntelligenceController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\BusinessIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessIntelligenceController extends Controller
{
    protected BusinessIntelligenceService $biService;

    public function __construct(BusinessIntelligenceService $biService)
    {
        $this->biService = $biService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        $insights = $this->biService->getBusinessInsights();
        return view('admin.bi.index', compact('insights'));
    }
}