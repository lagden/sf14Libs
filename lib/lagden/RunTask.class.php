<?php
class RunTask
{
    static public function cc()
    {
        chdir(sfConfig::get('sf_root_dir'));
        exec('./symfony cc -q');
    }
}
