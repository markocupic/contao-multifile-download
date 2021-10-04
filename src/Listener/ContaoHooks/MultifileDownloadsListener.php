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

namespace Markocupic\ContaoMultifileDownload\Listener\ContaoHooks;

use Contao\Config;
use Contao\ContentElement;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Input;
use Contao\StringUtil;
use Contao\ZipWriter;
use Markocupic\ContaoMultifileDownload\Logger\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\Security;

/**
 * @Hook(MultifileDownloadsListener::HOOK, priority=MultifileDownloadsListener::PRIORITY)
 */
class MultifileDownloadsListener
{
    public const HOOK = 'getContentElement';
    public const PRIORITY = 10;
    public const ARCHIVE_PATH = 'system/tmp';

    /**
     * @var ContentModel
     */
    protected $objElement;

    /**
     * Files that are selected in the content element.
     *
     * @var array
     */
    protected $arrValidFileIDS = [];

    /**
     * Files to download.
     *
     * @var array
     */
    protected $arrFileIDS = [];

    /**
     * @var Security
     */
    private $security;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Security $security, Logger $logger, string $projectDir)
    {
        $this->security = $security;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(ContentModel $objElement, string $strBuffer, ContentElement $element): string
    {
        if ($this->isAjaxRequest()) {
            $this->disableCache();
            $this->sendLanguageData();
        }

        if ('true' === Input::get('zipDownload') && '' !== Input::get('files') && (int) $objElement->id === (int) Input::get('elId')) {
            // Content Element Model
            $this->objElement = $objElement;

            // Get allowed and valid files
            // Files must have been selected in the content element!
            $this->arrValidFileIDS = $this->getValidFiles();

            // Get file IDS from $_GET
            $arrIds = explode(',', Input::get('files'));
            $error = 0;

            // Validate
            foreach ($arrIds as $fileId) {
                $oFile = FilesModel::findByPk($fileId);

                if (null === $oFile) {
                    $strText = sprintf('Couldn\'t find file with ID %s in tl_files. System stopped!', $fileId);
                    $this->logger->log($strText, LogLevel::ERROR, __METHOD__);
                    ++$error;
                    continue;
                }

                if (!\in_array($fileId, $this->arrValidFileIDS, true)) {
                    $strText = sprintf('User is not allowed to download file ID %s (path: "%s"). System stopped!', $fileId, $oFile->path);
                    $this->logger->log($strText, LogLevel::ERROR, __METHOD__);
                    ++$error;
                    continue;
                }

                if (!is_file($this->projectDir.'/'.$oFile->path)) {
                    $strText = sprintf('File with ID %s (path: "%s") does not exists in the filesystem. System stopped!', $fileId, $oFile->path, );
                    $this->logger->log($strText, LogLevel::ERROR, __METHOD__);
                    ++$error;
                    continue;
                }

                $this->arrFileIDS[] = $fileId;
            }

            if ($error > 0 || \count($this->arrFileIDS) < 1) {
                $strText = 'No valid files selected for the download!';
                $this->logger->log($strText, LogLevel::ERROR, __METHOD__);
                $response = new Response($strText, Response::HTTP_BAD_REQUEST);

                throw new ResponseException($response);
            }

            // Delete old/unused zip-archives
            $this->deleteOldArchives();

            // Send zip archive to the browser
            throw new ResponseException($this->sendZipFileToBrowser());
        }

        return $strBuffer;
    }

    private function isAjaxRequest(): bool
    {
        if (Environment::get('isAjaxRequest') && Input::get('ceDownloads') && Input::get('loadLanguageData')) {
            return true;
        }

        return false;
    }

    private function disableCache(): void
    {
        global $objPage;
        $objPage->noSearch;
    }

    /**
     * Sort get valid and allowed files.
     */
    private function getValidFiles(): array
    {
        $arrValidFileIDS = [];

        // Use the home directory of the current user as file source
        if ($this->objElement->useHomeDir && $this->hasLoggedInFrontendUser()) {
            $objUser = $this->getLoggedInFrontendUser();

            if ($objUser->assignDir && $objUser->homeDir) {
                $this->objElement->multiSRC = [$objUser->homeDir];
            }
        } else {
            $this->objElement->multiSRC = StringUtil::deserialize($this->objElement->multiSRC);
        }

        // Return if there are no files
        if (!\is_array($this->objElement->multiSRC) || empty($this->objElement->multiSRC)) {
            return [];
        }

        // Get the file entries from the database
        $objFiles = FilesModel::findMultipleByUuids($this->objElement->multiSRC);

        $files = [];

        $allowedDownload = trimsplit(',', strtolower(Config::get('allowedDownload')));

        // Get all files
        while ($objFiles->next()) {
            // Continue if the files has been processed or does not exist
            if (isset($files[$objFiles->path]) || !file_exists($this->projectDir.'/'.$objFiles->path)) {
                continue;
            }

            // Single files
            if ('file' === $objFiles->type) {
                $objFile = new File($objFiles->path);

                if (!\in_array($objFile->extension, $allowedDownload, true) || preg_match('/^meta(_[a-z]{2})?\.txt$/', $objFile->basename)) {
                    continue;
                }

                // Add the file
                $files[$objFiles->path] = [
                    'id' => $objFiles->id,
                ];

                $arrValidFileIDS[] = $objFiles->id;
            } // Folders
            else {
                $objSubfiles = FilesModel::findByPid($objFiles->uuid);

                if (null === $objSubfiles) {
                    continue;
                }

                while ($objSubfiles->next()) {
                    // Skip subfolders
                    if ('folder' === $objSubfiles->type) {
                        continue;
                    }

                    $objFile = new File($objSubfiles->path, true);

                    if (!\in_array($objFile->extension, $allowedDownload, true) || preg_match('/^meta(_[a-z]{2})?\.txt$/', $objFile->basename)) {
                        continue;
                    }

                    // Add the file
                    $files[$objSubfiles->path] = [
                        'id' => $objSubfiles->id,
                    ];
                    $arrValidFileIDS[] = $objSubfiles->id;
                }
            }
        }

        return $arrValidFileIDS;
    }

    private function hasLoggedInFrontendUser(): bool
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            return true;
        }

        return false;
    }

    private function getLoggedInFrontendUser(): ?FrontendUser
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            return $user;
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    private function sendZipFileToBrowser(): BinaryFileResponse
    {
        // Set zip-archive name/path
        $zipTargetPath = sprintf(
            '%s/downloads_multifile_%s_archive.zip',
            self::ARCHIVE_PATH,
            (string) time()
        );

        // Initialize archive object
        $zip = new ZipWriter($zipTargetPath);

        // Add files to zip-archive
        foreach ($this->arrFileIDS as $id) {
            $objFile = FilesModel::findByPk($id);

            if (null !== $objFile) {
                if (is_file($this->projectDir.'/'.$objFile->path)) {
                    $zip->addFile($objFile->path, $objFile->name);
                }
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();

        $response = new BinaryFileResponse($this->projectDir.'/'.$zipTargetPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($zipTargetPath));
        $response->headers->set('Content-Type', 'application/zip');

        return $response;
    }

    /**
     * Delete zip-archives.
     */
    private function deleteOldArchives(): void
    {
        $tmpDir = $this->projectDir.'/'.self::ARCHIVE_PATH;

        if (file_exists($tmpDir)) {
            $finder = (new Finder())
                ->files()->in($tmpDir)
                ->depth(0)
                ->ignoreDotFiles(true)
            ;

            if ($finder->hasResults()) {
                foreach ($finder as $file) {
                    if ('zip' === $file->getExtension() && 0 === strpos($file->getBasename(), 'downloads_multifile_', )) {
                        if ($file->getCTime() + 3600 < time()) {
                            unlink($file->getRealPath());
                        }
                    }
                }
            }
        }
    }

    /**
     * Send language data.
     */
    private function sendLanguageData(): void
    {
        Controller::loadLanguageFile('default');
        $json = ['done' => 'true'];
        $lang = $GLOBALS['TL_LANG']['CTE']['ce_downloads'];

        if ($lang && \is_array($lang)) {
            foreach ($GLOBALS['TL_LANG']['CTE']['ce_downloads'] as $k => $v) {
                $json[$k] = $v;
            }
        }

        $response = new JsonResponse($json);

        throw new ResponseException($response);
    }
}
