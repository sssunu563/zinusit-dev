<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\Vendor;
use App\Services\ErrorMessageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendorController extends Controller
{
    /**
     * Log vendor actions to ActionLog table
     */
    private function logVendor(string $actionType, Vendor $vendor, string $note, array $meta = []): void
    {
        try {
            ActionLog::create([
                'user_id'     => auth()->id(),
                'action_type' => $actionType,
                'item_type'   => Vendor::class,
                'item_id'     => $vendor->id,
                'note'        => $note,
                'log_meta'    => array_merge([
                    'vendor_name'    => $vendor->name,
                    'contact_person' => $vendor->contact_person,
                    'email'          => $vendor->email,
                    'phone'          => $vendor->phone,
                    'category'       => $vendor->category,
                ], $meta),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to write vendor action log', [
                'action'    => $actionType,
                'vendor_id' => $vendor->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        
        $vendors = Vendor::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Vendors/Index', [
            'vendors' => $vendors,
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'contact_person' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:120',
            'address' => 'nullable|string',
            'category' => 'nullable|string|max:64',
        ]);

        try {
            $vendor = Vendor::create($validated);

            // Log vendor creation
            $this->logVendor('created', $vendor, "Created vendor: {$vendor->name}");

            if ($request->wantsJson()) {
                return response()->json($vendor);
            }

            return redirect()->back()->with('success', 'Vendor berhasil ditambahkan.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'vendor_create');

            return redirect()->back()
                ->withErrors(['name' => ErrorMessageService::getUserFriendlyMessage($e, 'vendor_create')])
                ->withInput();
        }
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'contact_person' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:120',
            'address' => 'nullable|string',
            'category' => 'nullable|string|max:64',
        ]);

        try {
            // Capture old values for change tracking
            $changes = [];
            foreach ($validated as $key => $value) {
                if ($vendor->{$key} !== $value) {
                    $changes[$key] = ['old' => $vendor->{$key}, 'new' => $value];
                }
            }

            $vendor->update($validated);

            // Log vendor update
            $this->logVendor('updated', $vendor, "Updated vendor: {$vendor->name}", $changes);

            return redirect()->back()->with('success', 'Vendor berhasil diperbarui.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'vendor_update', ['vendor_id' => $vendor->id]);

            return redirect()->back()
                ->withErrors(['name' => ErrorMessageService::getUserFriendlyMessage($e, 'vendor_update')])
                ->withInput();
        }
    }

    public function destroy(Vendor $vendor)
    {
        try {
            // Log vendor deletion with pre-deletion data
            $vendorData = [
                'name'            => $vendor->name,
                'contact_person'  => $vendor->contact_person,
                'email'           => $vendor->email,
                'phone'           => $vendor->phone,
                'category'        => $vendor->category,
            ];
            
            $this->logVendor('deleted', $vendor, "Deleted vendor: {$vendor->name}", $vendorData);
            
            $vendor->delete();
            return redirect()->back()->with('success', 'Vendor berhasil dihapus.');
        } catch (\Exception $e) {
            ErrorMessageService::logError($e, 'vendor_delete', ['vendor_id' => $vendor->id]);

            return redirect()->back()
                ->with('error', ErrorMessageService::getUserFriendlyMessage($e, 'vendor_delete'));
        }
    }
}
