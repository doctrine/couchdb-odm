<?php

namespace Doctrine\CouchDB\View;

class FolderDesignDocument implements DesignDocument
{
    /**
     * @var string
     */
    private $folderPath;

    /**
     * @var array
     */
    private $data;

    public function __construct($folderPath)
    {
        $this->folderPath = realpath($folderPath);
    }

    public function getData()
    {
        if ($this->data === null) {
            $rdi = new \RecursiveDirectoryIterator($this->folderPath, \FilesystemIterator::CURRENT_AS_FILEINFO);
            $ri = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::LEAVES_ONLY);

            $this->data = array();
            foreach ($ri AS $path) {
                if (substr($path, -3) === ".js") {
                    $parts = explode("/", ltrim(str_replace($this->folderPath, '', str_replace(".js", "", $path)), '/'));

                    if (count($parts) == 3) {
                        $this->data[$parts[0]][$parts[1]][$parts[2]] = file_get_contents($path);
                    } else if (count($parts) == 2) {
                        $this->data[$parts[0]][$parts[1]] = file_get_contents($path);
                    }
                }
            }
        }

        return $this->data;
    }
}