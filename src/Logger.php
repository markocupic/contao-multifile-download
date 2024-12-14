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

namespace Markocupic\ContaoMultifileDownload;

use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LoggerInterface;

class Logger
{
    public function __construct(
        private readonly null|LoggerInterface $logger = null,
    ) {
    }

    public function log(string $strText, string $strLogLevel, string $strContaoLogLevel, string $strMethod): void
    {
        $this->logger?->log(
            $strLogLevel,
            $strText,
            [
                'contao' => new ContaoContext($strMethod, $strContaoLogLevel),
            ]
        );
    }
}
