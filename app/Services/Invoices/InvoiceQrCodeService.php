<?php

namespace App\Services\Invoices;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class InvoiceQrCodeService
{
    public function svg(string $payload, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($payload, 'UTF-8', ErrorCorrectionLevel::M());
    }

    public function dataUri(string $payload, int $size = 220): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($payload, $size));
    }
}
