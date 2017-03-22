<?php
namespace App\Process;


class FileLister extends Lister
{

    public function all()
    {
        $files  = collect($this->file->listContents('', true));
        $all = [];
        foreach($files as $file){
            if (substr($file['basename'],0,1) == '.') continue;

            if($file['type'] == 'dir' && !isset($all[$file['basename']])) {
                $all[$file['basename']] = [];
            }

            if ($file['type'] == 'file') {
                $serie = substr($file['path'],0,-(strlen($file['basename'])+1));
                if (!isset($all[$serie])) {
                    $all[$serie] = [];
                }
                array_push($all[$serie], $file['basename']);
            }
        }
        return collect($all);
    }


    public function series()
    {
        return $this->all()->keys();
    }


    public function lessons($lesson = false)
    {
        if ($lesson) {
            return collect($this->all()->keyBy($lesson)->first());
        }
        return $this->all()->values();
    }
}