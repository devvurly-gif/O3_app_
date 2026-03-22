<?php

namespace App\Http\Controllers\Api;

use App\Exports\DocumentsExport;
use App\Exports\PaymentsExport;
use App\Exports\ProductsExport;
use App\Exports\StockMouvementsExport;
use App\Exports\ThirdPartnersExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function products(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new ProductsExport($request->only('search', 'category_id', 'p_status')),
            'produits_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function documents(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new DocumentsExport($request->only('document_type', 'status', 'search')),
            'documents_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function thirdPartners(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new ThirdPartnersExport($request->only('tp_Role', 'search')),
            'partenaires_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function stockMouvements(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new StockMouvementsExport($request->only('direction', 'reason', 'product_id', 'warehouse_id')),
            'mouvements_stock_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function payments(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new PaymentsExport($request->only('method')),
            'paiements_' . now()->format('Ymd_His') . '.xlsx'
        );
    }
}
