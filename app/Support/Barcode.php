<?php

namespace App\Support;

/**
 * Code 128 barcodes (code set B: letters, digits and the usual signs), drawn as SVG.
 * The bar widths below are the standard table: each symbol is three bars and three spaces,
 * eleven modules wide; symbol 104 starts code set B and 106 is the stop sign.
 */
class Barcode
{
    /** bar, space, bar, space ... widths of symbols 0 to 106 */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212',
        '221213', '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221',
        '223211', '221132', '221231', '213212', '223112', '312131', '311222', '321122', '321221',
        '312212', '322112', '322211', '212123', '212321', '232121', '111323', '131123', '131321',
        '112313', '132113', '132311', '211313', '231113', '231311', '112133', '112331', '132131',
        '113123', '113321', '133121', '313121', '211331', '231131', '213113', '213311', '213131',
        '311123', '311321', '331121', '312113', '312311', '332111', '314111', '221411', '431111',
        '111224', '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111', '111242',
        '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311',
        '113141', '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;
    private const STOP = 106;

    /** Can this text be printed as a barcode? (1 to 40 printable ASCII characters) */
    public static function fits(?string $text): bool
    {
        return is_string($text) && preg_match('/^[\x20-\x7E]{1,40}$/', $text) === 1;
    }

    /**
     * The symbol numbers of a text: start, one per character, the check symbol, stop.
     *
     * @return int[]|null null when the text cannot be encoded
     */
    public static function symbols(?string $text): ?array
    {
        if (! self::fits($text)) {
            return null;
        }
        $symbols = [self::START_B];
        $sum = self::START_B;
        foreach (str_split($text) as $position => $character) {
            $value = ord($character) - 32;
            $symbols[] = $value;
            $sum += $value * ($position + 1);
        }
        $symbols[] = $sum % 103;
        $symbols[] = self::STOP;

        return $symbols;
    }

    /** The widths of the bars and spaces, starting with a bar. */
    public static function widths(?string $text): ?string
    {
        $symbols = self::symbols($text);

        return $symbols === null ? null : implode('', array_map(fn (int $symbol) => self::PATTERNS[$symbol], $symbols));
    }

    /** The barcode as an SVG picture that scales to the width it is given, or '' when the text does not fit. */
    public static function svg(?string $text, int $height = 40): string
    {
        $widths = self::widths($text);
        if ($widths === null) {
            return '';
        }
        $quiet = 10; // empty modules a scanner needs on each side
        $x = $quiet;
        $bars = '';
        foreach (str_split($widths) as $index => $width) {
            if ($index % 2 === 0) {
                $bars .= '<rect x="'.$x.'" y="0" width="'.$width.'" height="'.$height.'"/>';
            }
            $x += (int) $width;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.($x + $quiet).' '.$height.'" preserveAspectRatio="none" role="img" aria-label="'.e($text).'" fill="#000">'.$bars.'</svg>';
    }
}
