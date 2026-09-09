<?php

/**
 * Label Configuration
 * 
 * Supported label formats for printing assets.
 * All dimensions in millimeters (mm).
 */

return [
    'defaults' => [
        'format' => 'label_40x25',  // Default label format
    ],

    'formats' => [
        // Small labels (for tape/ribbon printers)
        'label_40x25' => [
            'name' => '40×25mm (Brother TZe)',
            'width' => 40,
            'height' => 25,
            'margin_top' => 1.5,
            'margin_right' => 1.5,
            'margin_bottom' => 1.5,
            'margin_left' => 1.5,
        ],

        // Avery 5160 (10 per page, 1×2.63")
        'avery_5160' => [
            'name' => 'Avery 5160 (1×2.63")',
            'width' => 25.4,      // 1"
            'height' => 66.7,     // 2.63"
            'margin_top' => 2,
            'margin_right' => 2,
            'margin_bottom' => 2,
            'margin_left' => 2,
        ],

        // Avery 5520 (20 per page, 1×2")
        'avery_5520' => [
            'name' => 'Avery 5520 (1×2")',
            'width' => 25.4,      // 1"
            'height' => 50.8,     // 2"
            'margin_top' => 2,
            'margin_right' => 2,
            'margin_bottom' => 2,
            'margin_left' => 2,
        ],

        // Avery 8160 (30 per page, 1×2.625")
        'avery_8160' => [
            'name' => 'Avery 8160 (1×2.625")',
            'width' => 25.4,      // 1"
            'height' => 66.6,     // 2.625"
            'margin_top' => 2,
            'margin_right' => 2,
            'margin_bottom' => 2,
            'margin_left' => 2,
        ],

        // 4×6 thermal label
        'thermal_4x6' => [
            'name' => '4×6" Thermal',
            'width' => 101.6,     // 4"
            'height' => 152.4,    // 6"
            'margin_top' => 3,
            'margin_right' => 3,
            'margin_bottom' => 3,
            'margin_left' => 3,
        ],

        // 2.25×1.25 (Zebra LP2824)
        'zebra_2_25x1_25' => [
            'name' => 'Zebra 2.25×1.25"',
            'width' => 57.15,     // 2.25"
            'height' => 31.75,    // 1.25"
            'margin_top' => 1,
            'margin_right' => 1,
            'margin_bottom' => 1,
            'margin_left' => 1,
        ],
    ],

    'qr_code' => [
        'enabled' => true,
        'size_percent' => 40,  // Size relative to label height
    ],

    'fields' => [
        'asset_tag' => true,
        'serial' => true,
        'name' => true,
        'status' => true,
    ],
];
