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

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\FilesystemItemIterator;
use Contao\CoreBundle\Filesystem\FilesystemUtil;
use Contao\CoreBundle\Filesystem\VirtualFilesystem;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\ZipWriter;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FilesHelper
{
    private const ARCHIVE_NAME_PATTERN = 'system/tmp/downloads_multifile_%s_archive.zip';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly VirtualFilesystem $filesStorage,
        private readonly string $uploadPath,
        private readonly string $projectDir,
    ) {
    }

    public function getFiles(Request $request, ContentModel $contentModel): array
    {
        $arrAllowed = [];

        foreach ($this->getFilesystemItems($contentModel) as $filesystemItem) {
            $arrAllowed[] = $filesystemItem->getPath();
        }

        // Grab base64 encoded file paths from $_GET
        $arrWanted = array_map('base64_decode', explode(',', $request->query->get('files', true)));

        if (empty($arrWanted)) {
            $strText = 'No files selected for the download!';

            throw new \Exception($strText);
        }

        // Check if user is allowed
        foreach ($arrWanted as $filePath) {
            if (!\in_array($filePath, $arrAllowed, true)) {
                $strText = sprintf('User is not allowed to download file "%s". System stopped!', $filePath);

                throw new \Exception($strText);
            }

            if (!is_file(Path::join($this->projectDir, $this->uploadPath, $filePath))) {
                $strText = sprintf('Only static files are supported. File "%s" does not exists in the filesystem. System stopped!', $filePath);

                throw new \Exception($strText);
            }
        }

        return array_map(fn ($path) => new \SplFileInfo(Path::join($this->projectDir, $this->uploadPath, $path)), $arrWanted);
    }

    /**
     * @param array<\SplFileInfo> $arrSplFileInfo
     *
     * @throws \Exception
     */
    public function getZipArchive(array $arrSplFileInfo): \SplFileInfo
    {
        // Set zip-archive name/path
        $targetPath = sprintf(self::ARCHIVE_NAME_PATTERN, (string) time());

        // Initialize archive object
        $zip = new ZipWriter($targetPath);

        // Add files to zip-archive
        foreach ($arrSplFileInfo as $splFileInfo) {
            $zip->addFile(Path::makeRelative($splFileInfo->getRealPath(), $this->projectDir), $splFileInfo->getBasename());
        }

        // Zip archive will be created only after closing object
        $zip->close();

        return new \SplFileInfo(Path::makeAbsolute($targetPath, $this->projectDir));
    }

    public function getBinaryFileResponse(\SplFileInfo $splFileInfo): BinaryFileResponse
    {
        $response = new BinaryFileResponse($splFileInfo->getRealPath());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $splFileInfo->getBasename());
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend();

        return $response;
    }

    /**
     * Retrieve selected filesystem items but filter out those, that do not match the
     * current DCA and configuration constraints.
     */
    protected function getFilesystemItems(ContentModel $model): FilesystemItemIterator
    {
        $homeDir = null;

        if ($model->useHomeDir) {
            $user = $this->security->getUser();

            if ($user instanceof FrontendUser && $user->assignDir && $user->homeDir) {
                $homeDir = $user->homeDir;
            }
        }

        $sources = $homeDir ?: $model->multiSRC;

        // Find filesystem items
        $filesystemItems = FilesystemUtil::listContentsFromSerialized($this->filesStorage, $sources);

        // Optionally filter out files without metadata
        if ($model->metaIgnore) {
            $filesystemItems = $filesystemItems->filter(
                static fn (FilesystemItem $item): bool => (bool) $item->getExtraMetadata()->getLocalized()?->getDefault(),
            );
        }

        return $this->applyDownloadableFileExtensionsFilter($filesystemItems);
    }

    protected function applyDownloadableFileExtensionsFilter(FilesystemItemIterator $filesystemItemIterator): FilesystemItemIterator
    {
        $this->framework->initialize();

        $allowedDownload = StringUtil::trimsplit(',', $this->getContaoAdapter(Config::class)->get('allowedDownload'));

        return $filesystemItemIterator->filter(
            static fn (FilesystemItem $item): bool => \in_array(
                Path::getExtension($item->getPath(), true),
                array_map(strtolower(...), $allowedDownload),
                true,
            ),
        );
    }

    protected function getContaoAdapter(string $class): Adapter
    {
        return $this->framework->getAdapter($class);
    }
}
