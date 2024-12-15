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

namespace Markocupic\ContaoMultifileDownload\Helper;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\JsonResponse;

class TranslationHelper
{
    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    public function getTranslations(): JsonResponse
    {
        $this->framework->initialize();
        $controller = $this->framework->getAdapter(Controller::class);
        $controller->loadLanguageFile('default');
        $trans = [];

        if (!empty($GLOBALS['TL_LANG']['CTE']['ce_downloads']) && \is_array($GLOBALS['TL_LANG']['CTE']['ce_downloads'])) {
            $lang = $GLOBALS['TL_LANG']['CTE']['ce_downloads'];

            foreach ($lang as $k => $v) {
                $trans[$k] = $v;
            }
        }

        return new JsonResponse($trans);
    }
}
