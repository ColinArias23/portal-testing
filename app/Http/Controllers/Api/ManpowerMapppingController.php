<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ManpowerMappingService;
use Illuminate\Http\Request;

class ManpowerMappingController extends Controller
{
  public function __construct(private ManpowerMappingService $service) {}

  // GET /api/manpower-mapping?division_id=&funding_source=&employment_type=&include_slots=1
  public function index(Request $request)
  {
    $filters = [
      'division_id' => $request->integer('division_id') ?: null,
      'funding_source' => $request->string('funding_source')->toString() ?: null,
      'employment_type' => $request->string('employment_type')->toString() ?: null,
      'include_slots' => $request->boolean('include_slots', true),
    ];

    return response()->json($this->service->build($filters));
  }
}
