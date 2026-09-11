<?php

namespace Tests\Unit;

use App\Services\ServerMonitorService;
use Tests\TestCase;

class ServerMonitorServiceMemoryConversionTest extends TestCase
{
    public function test_memory_free_percentage_is_returned_directly_from_available_memory_percent(): void
    {
        $this->assertSame(38.4, ServerMonitorService::memoryFreeFromAvailablePercent(38.4));
    }
}
