<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee5 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\ClassContent\Repository\Element;

use BackBee\BBApplication;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Element\File as ElementFile;
use BackBee\ClassContent\Exception\ClassContentException;
use BackBee\ClassContent\Repository\ClassContentRepository;
use BackBee\Util\Media;
use BackBee\Utils\File\File;

/**
 * file repository
 * @category    BackBee
 * @package     BackBee\ClassContent
 * @subpackage  Repository\Element
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 */
class FileRepository extends ClassContentRepository
{
    /**
     * The temporary directory
     * @var string
     */
    protected $_temporarydir;

    /**
     * The sotrage directory
     * @var string
     */
    protected $_storagedir;

    /**
     * The media library directory
     * @var string
     */
    protected $_mediadir;

    /**
     * The current application
     * @var \BackBee\BBApplication
     */
    private $_application;

    /**
     * Move an temporary uploaded file to either media library or storage directory
     * @param  \BackBee\ClassContent\Element\File $file
     * @return boolean
     */
    public function commitFile(ElementFile $file)
    {
        $filename = $file->path;
        File::resolveFilepath($filename, null, array('base_dir' => $this->_temporarydir));

        $currentname = Media::getPathFromContent($file);
        File::resolveFilepath($currentname, null, array('base_dir' => ($this->isInMediaLibrary($file)) ? $this->_mediadir : $this->_storagedir));

        try {
            File::move($filename, $currentname);
            $file->path = Media::getPathFromContent($file);
            $this->_dispatchPostUploadEvent($currentname, $file->path);
        } catch (\BackBee\Exception\BBException $e) {
            return false;
        }

        return true;
    }

    /**
     * Move an uploaded file to the temporary directory and update file content
     * @param  \BackBee\ClassContent\AbstractClassContent            $file
     * @param  string                                                $newfilename
     * @param  string                                                $originalname
     * @return boolean|string
     * @throws \BackBee\ClassContent\Exception\ClassContentException Occures on invalid content type provided
     */
    public function updateFile(AbstractClassContent $file, $newfilename, $originalname = null, $src = null)
    {
        if (false === ($file instanceof ElementFile)) {
            throw new ClassContentException('Invalid content type');
        }

        if (null === $originalname) {
            $originalname = $file->originalname;
        }

        $base_dir = $this->_temporarydir;
        $file->originalname = $originalname;
        $file->path = Media::getPathFromContent($file);

        if (null === $file->getDraft()) {
            $base_dir = ($this->isInMediaLibrary($file)) ? $this->_mediadir : $this->_storagedir;
        }

        $moveto = $file->path;
        File::resolveFilepath($moveto, null, array('base_dir' => $base_dir));

        try {
            if ($src === null) {
                File::resolveFilepath($newfilename, null, array('base_dir' => $this->_temporarydir));
                File::move($newfilename, $moveto);
            } else {
                $dir = dirname($moveto);
                if (!is_dir($dir)) {
                    File::mkdir($dir);
                }

                file_put_contents($moveto, base64_decode($src));
            }

            $this->_dispatchPostUploadEvent($moveto, $file->path);
        } catch (\BackBee\Exception\BBException $e) {
            return false;
        }

        //$stat = stat($moveto);
        //$file->setParam('stat', $stat, 'array');

        return $moveto;
    }

    /**
     * Return true if file is in media libray false otherwise
     * @param  \BackBee\ClassContent\Element\File $file
     * @return boolean
     */
    public function isInMediaLibrary(ElementFile $file)
    {
        $parent_ids = $this->getParentContentUid($file);
        if (0 === count($parent_ids)) {
            return false;
        }

        $q = $this->_em->getConnection()
                ->createQueryBuilder()
                ->select('m.id')
                ->from('media', 'm')
                ->andWhere('m.content_uid IN ("'.implode('","', $parent_ids).'")');

        $medias = $q->execute()->fetchAll(\PDO::FETCH_COLUMN);

        return (0 < count($medias)) ? $medias[0] : false;
    }

    /**
     * Do stuf on update by post of the content editing form
     * @param  \BackBee\ClassContent\AbstractClassContent            $content
     * @param  stdClass                                              $value
     * @param  \BackBee\ClassContent\AbstractClassContent            $parent
     * @return \BackBee\ClassContent\Element\File
     * @throws \BackBee\ClassContent\Exception\ClassContentException Occures on invalid content type provided
     */
    public function getValueFromPost(AbstractClassContent $content, $value, AbstractClassContent $parent = null)
    {
        if (false === ($content instanceof ElementFile)) {
            throw new ClassContentException('Invalid content type');
        }

        if (true === property_exists($value, 'value')) {
            $image_obj = json_decode($value->value);
            if (true === is_object($image_obj) && true === property_exists($image_obj, 'filename') && true === property_exists($image_obj, 'originalname')) {
                $this->updateFile($content, $image_obj->filename, $image_obj->originalname, $image_obj->src);
            }
        }

        return $content;
    }

    /**
     * Set the storage directories define by the BB5 application
     * @param  \BackBee\BBApplication                                  $application
     * @return \BackBee\ClassContent\Repository\Element\fileRepository
     */
    public function setDirectories(BBApplication $application = null)
    {
        if (null !== $application) {
            $this->_application = $application;

            $this->setTemporaryDir($application->getTemporaryDir())
                    ->setStorageDir($application->getStorageDir())
                    ->setMediaDir($application->getMediaDir());
        }

        return $this;
    }

    /**
     * Set the temporary directory
     * @param  type                                                    $temporary_dir
     * @return \BackBee\ClassContent\Repository\Element\fileRepository
     */
    public function setTemporaryDir($temporary_dir = null)
    {
        $this->_temporarydir = $temporary_dir;

        return $this;
    }

    /**
     * Set the storage directory
     * @param  type                                                    $storage_dir
     * @return \BackBee\ClassContent\Repository\Element\fileRepository
     */
    public function setStorageDir($storage_dir = null)
    {
        $this->_storagedir = $storage_dir;

        return $this;
    }

    /**
     * Set the media library directory
     * @param  type                                                    $media_dir
     * @return \BackBee\ClassContent\Repository\Element\fileRepository
     */
    public function setMediaDir($media_dir = null)
    {
        $this->_mediadir = $media_dir;

        return $this;
    }

    /**
     * Dispatch postupload event
     * @param string $sourcefile
     * @param string $targetfile
     */
    private function _dispatchPostUploadEvent($sourcefile, $targetfile)
    {
        if (null !== $this->_application &&
                null !== $this->_application->getEventDispatcher()) {
            $event = new \BackBee\Event\PostUploadEvent($sourcefile, $targetfile);
            $this->_application->getEventDispatcher()->dispatch('file.postupload', $event);
        }
    }
}