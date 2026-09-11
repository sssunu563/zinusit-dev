<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class InfraReportDataTest extends TestCase
{
    public function test_against_real_database(): void
    {
        $req = new \Illuminate\Http\Request(['from' => '2026-09-04', 'to' => '2026-09-10']);
        $ctrl = app(\App\Http\Controllers\Report\InfraReportController::class);
        $res = $ctrl->data($req);
        
        $json = json_decode($res->getContent(), true);
        dump($json['error'] ?? 'NO ERROR', $json);
        $this->assertArrayNotHasKey('error', $json);
    }
}
