<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CctvRegularReport extends Model
{
    use HasFactory;

    protected $table = 'cctv_regular_reports';

    protected $fillable = [
        'doc_no',
        'location',
        'checked_by',
        'checked_date',
        'week_number',
        'year',
        'areas_data',
        'loading_area_data',
        'beacukai_data',
        'maintenance_data',
        'signature_dept1_name',
        'signature_dept1_signer',
        'signature_dept1_image',
        'signature_dept1_at',
        'signature_dept2_name',
        'signature_dept2_signer',
        'signature_dept2_image',
        'signature_dept2_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'checked_date'        => 'date:Y-m-d',
        'week_number'         => 'integer',
        'year'                => 'integer',
        'areas_data'          => 'array',
        'loading_area_data'   => 'array',
        'beacukai_data'       => 'array',
        'maintenance_data'    => 'array',
        'signature_dept1_at'  => 'datetime',
        'signature_dept2_at'  => 'datetime',
    ];

    protected $appends = ['resolved_areas'];

    public function getResolvedAreasAttribute(): array
    {
        if (!empty($this->areas_data) && is_array($this->areas_data)) {
            return $this->areas_data;
        }

        $areas = [];
        if (!empty($this->loading_area_data)) {
            $areas[] = array_merge(['name' => 'Loading Area'], $this->loading_area_data);
        }
        if (!empty($this->beacukai_data)) {
            $areas[] = array_merge(['name' => 'Beacukai CCTV'], $this->beacukai_data);
        }

        return $areas;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getCompanyNameAttribute(): string
    {
        if (preg_match('/^ZDI/i', trim($this->location ?? ''))) {
            return 'PT. ZINUS DREAM INDONESIA';
        }
        return 'PT. ZINUS GLOBAL INDONESIA';
    }

    /**
     * Generate default Document ID in the format: IR/CCTV/{LOC}/{YEAR}/{WEEK}
     * e.g., IR/CCTV/ZGI/2026/37 or IR/CCTV/ZDI/2026/37
     */
    public static function generateDocNo(?string $location = null, ?int $year = null, ?int $week = null): string
    {
        $year = $year ?: (int) date('Y');
        $week = $week ?: (int) Carbon::now()->isoWeek();
        
        $code = 'ZGI';
        if ($location && preg_match('/^ZDI/i', trim($location))) {
            $code = 'ZDI';
        } elseif ($location && preg_match('/^(ZGI|ZINUS)/i', trim($location))) {
            $code = 'ZGI';
        }

        return sprintf('IR/CCTV/%s/%d/%d', $code, $year, $week);
    }
}
