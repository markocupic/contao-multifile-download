<?php

/*
 * This file is part of Contao Multi File Download.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-multifile-download
 */

if (TL_MODE === 'FE')
{
    // Resources
    $GLOBALS['TL_CSS'][] = 'bundles/markocupiccontaomultifiledownload/ce_downloads_multifile.css';
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupiccontaomultifiledownload/ce_downloads_multifile.js';
}