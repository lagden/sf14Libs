<?php
namespace lagden;
use Doctrine_Core as Doctrine_Core;

class RelationList
{
    /**
    *
    * @return integer
    * @author Thiago Lagden
    */
    public static function modelo($m,$id,$rels,$field="name")
    {
        $obj = Doctrine_Core::getTable($m)->find($id);
        $arr = array();
        if($obj)
        {
            foreach($obj->$rels as $rel)
            {
                $arr[]=$rel->$field;
            }
        }
        return json_encode($arr);
    }

    public static function lista($m,$field="name")
    {
        $items = Doctrine_Core::getTable($m)->findAll();
        $arr = array();
        foreach($items as $item)
        {
            $arr[]=$item->$field;
        }
        return json_encode($arr);
    }

    public static function findByNameOrCreate($els,$model,$field="name",$create=true)
    {
        if (! is_array($els))
        {
            $els = preg_split('/\s*,\s*/', $els, null, PREG_SPLIT_NO_EMPTY);
        }
        
        $table = Doctrine_Core::getTable($model);
        $output = array();
        foreach ($els as $el)
        {
            $item = $table->findOneByName($el);
            if(!$item && $create)
            {
                $item = new $model;
                $item->$field = $el;
                $item->save();
            }
            if($item) $output[]=$item->id;
        }
        return $output;
    }
}