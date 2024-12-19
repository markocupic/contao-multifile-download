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

namespace Markocupic\ContaoMultifileDownload\EventListener\ContaoHooks;

use Contao\ContentModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Exception\ResponseException;
use Markocupic\ContaoMultifileDownload\Helper\FilesHelper;
use Markocupic\ContaoMultifileDownload\Helper\TranslationHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsHook(MultifileDownloadsListener::HOOK, priority: 100)]
class MultifileDownloadsListener
{
    public const HOOK = 'getContentElement';
    private static bool $disableHook = false;

    public function __construct(
        private readonly FilesHelper $filesHelper,
        private readonly RequestStack $requestStack,
        private readonly TranslationHelper $translationHelper,
        private readonly ?LoggerInterface $contaoErrorLogger = null,
    ) {
    }

    /**
     * Do not type hint the third argument $element.
     *
     * @param $contentElement
     */
    public function __invoke(ContentModel $contentModel, string $strBuffer, $contentElement): string
    {
        // The hook can be disabled.
        if (static::$disableHook) {
            return $strBuffer;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($this->isGetLanguageDataRequest($request, $contentModel)) {
            // Send translations as json response.
            throw new ResponseException($this->translationHelper->getTranslations());
        }

        if ($this->isSendFilesToBrowserRequest($request, $contentModel)) {
            try {
                // Validate files.
                $arrFiles = $this->filesHelper->getFiles($request, $contentModel);
            } catch (\Exception $e) {
                $this->contaoErrorLogger?->error($e->getMessage());
                $response = new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);

                throw new ResponseException($response);
            }

            // Get the zip archive.
            $zipArchive = $this->filesHelper->getZipArchive($arrFiles);

            // Send the zip archive to the browser.
            throw new ResponseException($this->filesHelper->getBinaryFileResponse($zipArchive));
        }

        return $strBuffer;
    }

    public static function disableHook(): void
    {
        self::$disableHook = true;
    }

    public static function enableHook(): void
    {
        self::$disableHook = false;
    }

    public static function isEnabled(): bool
    {
        return self::$disableHook;
    }

    protected function isGetLanguageDataRequest(Request $request, ContentModel $contentModel): bool
    {
        if ('downloads' !== $contentModel->type) {
            return false;
        }

        if (!$request->isXmlHttpRequest()) {
            return false;
        }

        if ($contentModel->id !== (int) $request->query->get('ce_id')) {
            return false;
        }

        if (!$request->query->has('load_language_data')) {
            return false;
        }

        if (!$request->query->has('content_downloads')) {
            return false;
        }

        return true;
    }

    protected function isSendFilesToBrowserRequest(Request $request, ContentModel $contentModel): bool
    {
        if ('downloads' !== $contentModel->type) {
            return false;
        }

        if ('true' !== $request->query->get('multifile_download')) {
            return false;
        }

        if (empty($request->query->get('files'))) {
            return false;
        }

        if ($contentModel->id !== (int) $request->query->get('el_id')) {
            return false;
        }

        return true;
    }
}
