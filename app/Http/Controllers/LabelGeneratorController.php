<?php

namespace App\Http\Controllers;

use App\Services\SnipeItService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabelGeneratorController extends Controller
{
    public function __construct(
        protected SnipeItService $snipe
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $assets = [];

        if ($search !== '') {
            $payload = $this->snipe->getHardware([
                'search' => $search,
                'limit'  => 30,
            ]);
            $rawRows = $payload['rows'] ?? [];

            $assets = array_map(function ($row) {
                return [
                    'id'          => $row['id'] ?? 0,
                    'name'        => $row['name'] ?? '',
                    'asset_tag'   => $row['asset_tag'] ?? '',
                    'serial'      => $row['serial'] ?? '',
                    'model'       => data_get($row, 'model.name', ''),
                    'category'    => data_get($row, 'category.name', ''),
                    'location'    => data_get($row, 'location.name', ''),
                    'company'     => data_get($row, 'company.name', ''),
                    'status'      => data_get($row, 'status_label.name', ''),
                    'status_type' => data_get($row, 'status_label.status_type', ''),
                    'qr_url'      => url('a/' . ($row['serial'] ?: $row['asset_tag'] ?: $row['id'])),
                ];
            }, $rawRows);
        }

        return Inertia::render('LabelGenerator/Index', [
            'assets'       => $assets,
            'initialSearch' => $search,
        ]);
    }
}
