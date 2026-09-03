<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'location', 'user', 'email', 'leader', 'company', 'department', 'dept_head',
        'it_staff',
        'report_id', 'change_time', 'report_type', 'date',
        'device_category', 'device_name', 'asset_tag', 'serial_number', 'asset_snapshot',
        'checked_by', 'approve_by', 'checked_date',
        'issue_description', 'solution', 'remarks', 'photo',
        'it_signature', 'checked_signature', 'user_signature', 'leader_signature', 'signature_date',
        'completed_at', 'snipeit_synced_at', 'snipeit_sync_status', 'snipeit_sync_log',
        'snipeit_asset_id', 'completed_pdf_path',
        'user_snipeit_id', 'it_staff_id', 'checked_by_id',
    ];

    protected $casts = [
        'change_time'       => 'datetime',
        'date'              => 'date',
        'checked_date'      => 'date',
        'signature_date'    => 'date',
        'completed_at'      => 'datetime',
        'snipeit_synced_at' => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'it_signature'      => 'encrypted',
        'checked_signature' => 'encrypted',
        'user_signature'    => 'encrypted',
        'leader_signature'  => 'encrypted',
    ];
}
