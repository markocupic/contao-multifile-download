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
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\File;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\ZipWriter;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FilesHelper
{
    private const ARCHIVE_NAME_PATTERN = 'downloads_multifile_%s_archive.zip';
    private const TEMPORARY_FOLDER = 'system/tmp';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly LoggerInterface $contaoErrorLogger,
        private readonly Security $security,
        private readonly string $projectDir,
    ) {
    }

    public function getFiles(Request $request, ContentModel $contentModel): Response|BinaryFileResponse
    {
        $arrFileIds = [];

        // Get allowed and valid files.
        // Files must be selected in the content element!
        $arrValidFileIds = $this->getAllowedFileIds($contentModel);

        // Get file ids from $_GET
        $arrFilePaths = array_map('base64_decode', explode(',', $request->query->get('files', true)));

        $filesModel = $this->framework->getAdapter(FilesModel::class);

        // Validate
        foreach ($arrFilePaths as $filePath) {
            $objFile = $filesModel->findByPath(Path::join('files', $filePath));

            if (null === $objFile) {
                $strText = sprintf('Could not find file with path %s in tl_files. System stopped!', $filePath);
                $this->contaoErrorLogger->error($strText);

                return new Response($strText, Response::HTTP_BAD_REQUEST);
            }

            $fileId = $objFile->id;

            if (!\in_array($fileId, $arrValidFileIds, true)) {
                $strText = sprintf('User is not allowed to download file ID %s (path: "%s"). System stopped!', $fileId, $objFile->path);
                $this->contaoErrorLogger->error($strText);

                return new Response($strText, Response::HTTP_BAD_REQUEST);
            }

            if (!is_file(Path::join($this->projectDir, $objFile->path))) {
                $strText = sprintf('File with ID %s (path: "%s") does not exists in the filesystem. System stopped!', $fileId, $objFile->path);
                $this->contaoErrorLogger->error($strText);

                return new Response($strText, Response::HTTP_BAD_REQUEST);
            }

            $arrFileIds[] = $fileId;
        }

        if (empty($arrFileIds)) {
            $strText = 'No valid files selected for the download!';
            $this->contaoErrorLogger->error($strText);

            return new Response($strText, Response::HTTP_BAD_REQUEST);
        }

        // Send zip archive to the browser
        return $this->getFileResponse($arrFileIds);
    }

    /**
     * @throws \Exception
     */
    protected function getAllowedFileIds(ContentModel $contentModel): array
    {
        $arrValidFileIds = [];

        $user = $this->security->getUser();

        // Use the home directory of the current user as file source
        if ($contentModel->useHomeDir && $user instanceof FrontendUser) {
            if ($user->assignDir && $user->homeDir) {
                $contentModel->multiSRC = [$user->homeDir];
            }
        } else {
            $stringUtil = $this->framework->getAdapter(StringUtil::class);
            $contentModel->multiSRC = $stringUtil->deserialize($contentModel->multiSRC, true);
        }

        // Return if there are no files
        if (!\is_array($contentModel->multiSRC) || empty($contentModel->multiSRC)) {
            return [];
        }

        $filesModel = $this->framework->getAdapter(FilesModel::class);

        // Get the file entries from the database
        $objFiles = $filesModel->findMultipleByUuids($contentModel->multiSRC);

        $files = [];

        $config = $this->framework->getAdapter(Config::class);

        $allowedDownloads = explode(',', strtolower(trim((string) $config->get('allowedDownload'))));

        // Get all files
        while ($objFiles->next()) {
            // Continue if the files has been processed or does not exist
            if (isset($files[$objFiles->path]) || !file_exists(Path::join($this->projectDir, $objFiles->path))) {
                continue;
            }

            // Add files
            if ('file' === $objFiles->type) {
                $objFile = new File($objFiles->path);

                if (!\in_array($objFile->extension, $allowedDownloads, true) || preg_match('/^meta(_[a-z]{2})?\.txt$/', $objFile->basename)) {
                    continue;
                }

                $files[$objFiles->path] = [
                    'id' => $objFiles->id,
                ];

                $arrValidFileIds[] = $objFiles->id;
            } else {
                // Add files in a folder
                $objSubfiles = $filesModel->findByPid($objFiles->uuid);

                if (null === $objSubfiles) {
                    continue;
                }

                while ($objSubfiles->next()) {
                    // Skip subdirectories
                    if ('folder' === $objSubfiles->type) {
                        continue;
                    }

                    $objFile = new File($objSubfiles->path);

                    if (!\in_array($objFile->extension, $allowedDownloads, true) || preg_match('/^meta(_[a-z]{2})?\.txt$/', $objFile->basename)) {
                        continue;
                    }

                    // Add the file
                    $files[$objSubfiles->path] = [
                        'id' => $objSubfiles->id,
                    ];
                    $arrValidFileIds[] = $objSubfiles->id;
                }
            }
        }

        return $arrValidFileIds;
    }

    /**
     * @throws \Exception
     */
    protected function getFileResponse(array $fileIds): BinaryFileResponse
    {
        // Set zip-archive name/path
        $zipTargetPath = sprintf(
            '%s/'.self::ARCHIVE_NAME_PATTERN,
            self::TEMPORARY_FOLDER,
            (string) time()
        );

        // Initialize archive object
        $zip = new ZipWriter($zipTargetPath);

        $filesModel = $this->framework->getAdapter(FilesModel::class);

        // Add files to zip-archive
        foreach ($fileIds as $id) {
            $objFile = $filesModel->findByPk($id);

            if (null !== $objFile) {
                if (is_file(Path::join($this->projectDir, $objFile->path))) {
                    $zip->addFile($objFile->path, $objFile->name);
                }
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();

        $response = new BinaryFileResponse(Path::join($this->projectDir, $zipTargetPath));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($zipTargetPath));
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend();

        return $response;
    }

    private function getLanguageData(): array
    {
        $controller = $this->framework->getAdapter(Controller::class);
        $controller->loadLanguageFile('default');

        $arrJson = [];
        $arrJson['done'] = 'true';
        $lang = $GLOBALS['TL_LANG']['CTE']['ce_downloads'];

        if ($lang && \is_array($lang)) {
            foreach ($GLOBALS['TL_LANG']['CTE']['ce_downloads'] as $k => $v) {
                $arrJson[$k] = $v;
            }
        }

        return $arrJson;
    }
}
