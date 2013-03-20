<?php
namespace lagden;

use lagden\GetFile as GetFile;

// Depedencies symfony 1.4 libs

class SaveFile
{
    static public $bin;
    /*

    $param - 'id' onde seragravado, 'name' Nome do campo mestre, 'lang' Ligunga, 'parent' se o id é de um parent
    $dir - diretorio onde será salvo
    $clean - Limpa o diretorio - GLOB
    $exec - Executa bashscript
    $bash - nome do script
    $unlink - remove o arquivo gerado ao finalizar
    $customName - nome customizado para o newFilename

    // */
    static public function save($param, $dir, $clean=false, $exec=false, $bash=null, $unlink=true, $customName=false)
    {
        $defaultParam = array(
            'id' => null,
            'name' => null,
            'lang' => null,
            'parent' => false,
        );

        // merge param
        $resultMerge = array_merge($defaultParam, $param);

        $selfId = $resultMerge['id'];
        $selfName = $resultMerge['name'];
        $selfLang = $resultMerge['lang'];
        $parent = $resultMerge['parent'];

        $ds=DIRECTORY_SEPARATOR;
        $windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        $bin = static::$bin = sfConfig::get('sf_root_dir')."{$ds}bin{$ds}";

        // verifica se o arquivo
        if ( $selfName && (!preg_match("/\//i", $selfName) || preg_match("/\.\.\/test/i", $selfName)) )
        {
            $recordDir = static::encontreNoGrupo("{$dir}", "{$selfId}");
            if($recordDir)
            {
                if($clean!=false) GetFile::cleanDir($recordDir, $clean);
            }
            else
            {
                $recordDir = static::encontreGrupoDisponivel("{$dir}", "{$selfId}");
                exec("mkdir -p $recordDir");
            }

            preg_match("/[0-9a-f]{32}/i", $recordDir, $matches);
            $hash = (isset($matches[0])) ? $matches[0] : null;

            // Verifica se existe o hash
            if(!$hash) die('SaveFile error: Missing hash');
            else $dir = "{$dir}{$ds}{$hash}";

            // move the original file to the record folder
            $uploaded = GetFile::getUploadBasePath().$selfName;

            $parts = pathinfo($uploaded);
            $newExt = $parts['extension'];

            $partsExt = ($parent) ? "{$selfLang}.{$parts['extension']}" : "{$parts['extension']}";

            // Final file name
            $newFilename = ($exec) ? "original.{$partsExt}" : basename($uploaded);
            $newFilename = ($customName) ? "{$customName}.{$partsExt}" : $newFilename;
            $newLocalFile = "{$recordDir}{$ds}{$newFilename}";

            // move o arquivo para o local
            static::mv("{$uploaded}", $newLocalFile, $windows);

            // Gera outros tamanhos - imagemagick bashscript
            if($exec)
            {
                if ($windows)
                {
                    $dir = str_replace("\\", "/", $dir);
                    $dir = preg_replace("/([a-zA-Z])\:\//", "/$1/", $dir);
                    $cmd = "sh {$bin}{$bash} {$selfId} {$dir}";
                }
                else
                    $cmd = "{$bin}{$bash} {$selfId} {$dir}";

                $return_var = 0;
                $cmd = ($parent) ? "{$cmd} {$selfLang}": $cmd;
                exec("{$cmd}", $out, $return_var);
                if($return_var === 0)
                    $newExt = !empty($out) ? $out[0] : null;
                else
                    die("SaveFile error: {$cmd} | exit: {$return_var}");

                // remove o arquivo
                if($unlink && file_exists($newLocalFile))
                    unlink($newLocalFile);
            }
            return array('hash'=>$hash,'id'=>$selfId,'ext'=>$newExt);
        }
        return false;
    }

    static private function mv($o, $n, $windows = false)
    {
        if($windows)
        {
            $bin = static::$bin;
            $cmd = "sh {$bin}move {$o} {$n}";
        }
        else
            $cmd = "mv {$o} {$n}";

        exec("{$cmd}");
    }

    static private function encontreNoGrupo($dir, $id)
    {
        $ds = DIRECTORY_SEPARATOR;
        $r = glob("{$dir}{$ds}{*}{$ds}{$id}", GLOB_ONLYDIR | GLOB_BRACE | GLOB_NOSORT);
        return isset($r[0]) ? $r[0] : null;
    }

    // estrutura de arquivos agrupado por hash - cada grupo de hash(dir) suporta até 1000 diretórios
    static private function encontreGrupoDisponivel($dir, $id)
    {
        $ds = DIRECTORY_SEPARATOR;
        $rs = glob("{$dir}{$ds}{*}", GLOB_ONLYDIR | GLOB_BRACE | GLOB_NOSORT);
        if(!empty($rs))
        {
            foreach ($rs as $r)
            {
                if(preg_match("/[0-9a-f]{32}/i", $r))
                {
                    $totalDirs = glob("{$r}{$ds}{*}", GLOB_ONLYDIR | GLOB_BRACE | GLOB_NOSORT);
                    if(count($totalDirs) < 1000)
                        return "{$r}{$ds}{$id}";
                }
            }
        }
        $hash = md5(mt_rand().time());
        return "{$dir}{$ds}{$hash}{$ds}{$id}";
    }
}
