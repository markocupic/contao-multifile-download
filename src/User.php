<?php

declare(strict_types=1);

/*
 * This file is part of Contao Multi File Download.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-multifile-download
 */

namespace Markocupic\ContaoMultifileDownload;

use Contao\FrontendUser;
use Symfony\Component\Security\Core\Security;

class User
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function hasLoggedInFrontendUser(): bool
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            return true;
        }

        return false;
    }

    public function getLoggedInFrontendUser(): ?FrontendUser
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            return $user;
        }

        return null;
    }
}
