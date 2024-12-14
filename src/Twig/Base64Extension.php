<?php

declare(strict_types=1);

/*
 * This file is part of Contao Multifile Download.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-multifile-download
 */

namespace Markocupic\ContaoMultifileDownload\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Base64Extension extends AbstractExtension
{

    public function getFilters(): array
    {
        return [
            new TwigFilter('cmf_base64_encode', [$this, 'base64Encode']),
        ];
    }

    public function base64Encode(string $string): string
    {
        return base64_encode($string);
    }
}
