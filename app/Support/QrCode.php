<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

final class QrCode
{
    /** Vector QR in Carbone, transparent background, ready for print. */
    public static function svg(string $content, string $color = '#2A1E19'): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            // M: still readable with a scratch or rain drop on the sign
            'eccLevel' => EccLevel::M,
            'drawLightModules' => false,
            'connectPaths' => true,
            'quietzoneSize' => 2,
        ]);

        return str_replace('fill="#000"', 'fill="'.$color.'"', (new Generator($options))->render($content));
    }
}
