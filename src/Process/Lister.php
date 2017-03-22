<?php
namespace App\Process;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\Plugin\ListPaths;

abstract class Lister
{
    public $file;

    public function __construct()
    {
        $this->file = new Filesystem(
            new Adapter(getenv('DOWNLOAD_FOLDER'))
        );


    }
    abstract public function all();
    abstract public function series();
    abstract public function lessons();
}