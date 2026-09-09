<?php

namespace App\Services;

use TCPDF;
use Illuminate\Support\Facades\Config;

class LabelGeneratorService
{
    protected array $labelConfig;
    protected string $format;
    protected array $formatSpec;

    public function __construct(string $format = null)
    {
        $this->labelConfig = Config::get('labels');
        $this->format = $format ?? $this->labelConfig['defaults']['format'];
        $this->formatSpec = $this->labelConfig['formats'][$this->format] ?? $this->labelConfig['formats']['label_40x25'];
    }

    public function generatePdf(array $assets): string
    {
        $pdf = new TCPDF(
            $this->formatSpec['width'] > $this->formatSpec['height'] ? 'L' : 'P',
            'mm',
            [$this->formatSpec['width'], $this->formatSpec['height']]
        );

        $pdf->SetDefaultMonospacedFont(TCPDF_FONT_MONOSPACED);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetFont('helvetica', '', 8);

        $totalLabels = count($assets);
        foreach ($assets as $index => $asset) {
            if ($index > 0) {
                $pdf->AddPage();
            }

            $this->drawLabel($pdf, $asset);
        }

        return $pdf->Output('', 'S');
    }

    protected function drawLabel(TCPDF $pdf, array $asset): void
    {
        $w = $this->formatSpec['width'];
        $h = $this->formatSpec['height'];
        $mt = $this->formatSpec['margin_top'];
        $ml = $this->formatSpec['margin_left'];
        $mr = $this->formatSpec['margin_right'];
        $mb = $this->formatSpec['margin_bottom'];

        $availableWidth = $w - $ml - $mr;
        $availableHeight = $h - $mt - $mb;

        // Calculate QR code size
        $qrSize = $availableHeight * ($this->labelConfig['qr_code']['size_percent'] / 100);
        $infoWidth = $availableWidth - $qrSize - 1; // 1mm gap

        // Draw QR code
        if ($this->labelConfig['qr_code']['enabled'] && !empty($asset['qr_url'])) {
            $qrX = $ml;
            $qrY = $mt + ($availableHeight - $qrSize) / 2;
            
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(
                $qrSize,
                $qrSize,
                $this->getQRCodeHTML($asset['qr_url']),
                0,
                0,
                'C',
                false,
                '',
                0,
                false,
                'T',
                'M'
            );
        }

        // Draw asset info text
        $textX = $ml + $qrSize + 1;
        $textY = $mt;
        $textWidth = $infoWidth;

        $pdf->SetXY($textX, $textY);
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->MultiCell($textWidth, 3, $asset['name'] ?? '', 0, 'L', false, 1, $textX, $textY);

        if (!empty($asset['asset_tag'])) {
            $pdf->SetFont('courier', '', 4.5);
            $pdf->SetXY($textX, $pdf->GetY());
            $pdf->MultiCell($textWidth, 2, $asset['asset_tag'], 0, 'L', false, 1, $textX);
        }

        if (!empty($asset['serial'])) {
            $pdf->SetFont('courier', 'I', 4);
            $pdf->SetXY($textX, $pdf->GetY());
            $pdf->MultiCell($textWidth, 2, 'SN: ' . $asset['serial'], 0, 'L', false, 1, $textX);
        }

        if (!empty($asset['status'])) {
            $pdf->SetFont('helvetica', 'B', 4);
            $pdf->SetXY($textX, $pdf->GetY());
            $pdf->SetTextColor(68, 68, 68);
            $pdf->MultiCell($textWidth, 2, strtoupper($asset['status']), 0, 'L', false, 1, $textX);
            $pdf->SetTextColor(0, 0, 0);
        }
    }

    protected function getQRCodeHTML(string $url): string
    {
        // This will be replaced by actual QR code generation
        // For now, return placeholder
        return '[QR]';
    }

    public function getAvailableFormats(): array
    {
        return array_map(fn($key, $spec) => [
            'id' => $key,
            'name' => $spec['name'],
            'width' => $spec['width'],
            'height' => $spec['height'],
        ], array_keys($this->labelConfig['formats']), $this->labelConfig['formats']);
    }

    public function getFormatSpec(string $format): array
    {
        return $this->labelConfig['formats'][$format] ?? $this->labelConfig['formats']['label_40x25'];
    }
}
