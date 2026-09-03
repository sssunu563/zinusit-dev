<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\Procurement;
use App\Models\Vendor;
use App\Services\ErrorMessageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProcurementController extends Controller
{
    /**
     * Log procurement actions to ActionLog table
     */
    private function logProcurement(string $actionType, Procurement $procurement, string $note, array $meta = []): void
    {
        try {
            ActionLog::create([
                'user_id'     => auth()->id(),
                'action_type' => $actionType,
                'item_type'   => Procurement::class,
                'item_id'     => $procurement->id,
                'note'        => $note,
                'log_meta'    => array_merge([
                    'title'             => $procurement->title,
                    'request_number'    => $procurement->request_number,
                    'requester_name'    => $procurement->requester_name,
                    'department'        => $procurement->department,
                    'status'            => $procurement->status,
                    'vendor_id'         => $procurement->vendor_id,
                    'estimated_cost'    => $procurement->estimated_cost,
                    'actual_cost'       => $procurement->actual_cost,
                ], $meta),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to write procurement action log', [
                'action'          => $actionType,
                'procurement_id'  => $procurement->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    public function index(Request $request)
    {
        $procurements = Procurement::query()
            ->with(['vendor:id,name', 'creator:id,name'])
            ->when($request->search, function($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                      ->orWhere('request_number', 'like', "%{$search}%")
                      ->orWhere('requester_name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15);

        return Inertia::render('Procurement/Index', [
            'procurements' => $procurements,
            'vendors' => Vendor::select('id', 'name')->get(),
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'request_number' => 'required|string|unique:procurements',
            'requester_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'estimated_cost' => 'nullable|numeric',
            'actual_cost' => 'nullable|numeric',
            'status' => 'required|string',
            'request_date' => 'required|date',
            'purchase_date' => 'nullable|date',
            'po_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'vendor_id' => 'nullable|exists:vendors,id',
        ]);

        try {
            $procurement = Procurement::create([
                ...$validated,
                'created_by' => $request->user()->id,
            ]);

            // Log procurement creation
            $this->logProcurement('created', $procurement, "Created procurement: {$procurement->title}");

            return redirect()->route('procurement.index')->with('success', 'Rekap pengadaan berhasil disimpan.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'procurement_create');

            return redirect()->back()
                ->withErrors(['title' => ErrorMessageService::getUserFriendlyMessage($e, 'procurement_create')])
                ->withInput();
        }
    }

    public function update(Request $request, Procurement $procurement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'requester_name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'estimated_cost' => 'nullable|numeric',
            'actual_cost' => 'nullable|numeric',
            'status' => 'required|string',
            'request_date' => 'required|date',
            'purchase_date' => 'nullable|date',
            'po_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'vendor_id' => 'nullable|exists:vendors,id',
        ]);

        try {
            // Capture changes for tracking
            $changes = [];
            foreach ($validated as $key => $value) {
                if ($procurement->{$key} !== $value) {
                    $changes[$key] = ['old' => $procurement->{$key}, 'new' => $value];
                }
            }

            $procurement->update($validated);

            // Log procurement update
            $this->logProcurement('updated', $procurement, "Updated procurement: {$procurement->title}", $changes);

            return redirect()->route('procurement.index')->with('success', 'Data pengadaan berhasil diperbarui.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'procurement_update', ['procurement_id' => $procurement->id]);

            return redirect()->back()
                ->withErrors(['title' => ErrorMessageService::getUserFriendlyMessage($e, 'procurement_update')])
                ->withInput();
        }
    }
}
