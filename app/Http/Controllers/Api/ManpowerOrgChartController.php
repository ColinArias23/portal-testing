<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ManpowerOrgChartService;
use Illuminate\Http\Request;

class ManpowerOrgChartController extends Controller
{
  public function __construct(private ManpowerOrgChartService $service) {}

  // GET /api/manpower-orgchart?division_id=1
  public function index(Request $request)
  {
    $divisionId = $request->integer('division_id');
    return response()->json($this->service->build($divisionId ?: null));
  }
}
