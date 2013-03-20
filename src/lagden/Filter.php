<?php
namespace lagden;

use lagden\Utils as Utils;
use lagden\Xtras as Xtras;
use sfConfig as sfConfig;
use sfContext as sfContext;

// Depedencies symfony 1.4 libs

class Filter
{
    /**
    *
    * @author Thiago Lagden
    */
    static public function query($filters, $fields, $table, $name='q')
    {
        $q = $table->getListQuery();
        $alias = $q->getRootAlias();

        if (isset($filters[$name]) && $filters[$name])
        {
            try
            {
                // Removendo palavras pequenas
                $fix = static::fix($filters[$name]);

                // Try Searchable
                $search = $table->search($fix);
                $arr = array();

                foreach($search as $v)
                    $arr[] = $v['id'];

                if(count($arr)>0)
                    $q->orWhereIn("{$alias}.id", $arr);
            }
            catch(Exception $e)
            {
                // No Searchable
            }

            // Or Array
            $buildOr = array();

            foreach($fields as $field)
            {
                $buildOr[] = "{$alias}.{$field} LIKE '%{$filters[$name]}%'";
            }

            // Add $buildOr in query
            if(count($buildOr)) $q->andWhere(join(' OR ',$buildOr));
        }

        // Added table fields
        if(!empty($filters))
        {
            foreach($filters as $k => $v)
            {
                if($k != 'q' && Utils::stringIsNullOrEmpty($v) == false)
                {
                    $q->andWhere("{$alias}.{$k} = ?", $v);
                }
            }
        }

        // Sort
        $order = sfContext::getInstance()->getUser()->getAttribute(sfConfig::get('order_by'), sfConfig::get('order_by_default','id'));
        $direction = sfContext::getInstance()->getUser()->getAttribute(sfConfig::get('order_by_direction'), sfConfig::get('order_by_direction_default','DESC'));
        $q->orderBy("{$alias}.{$order} $direction");
        
        return $q;
    }

    static public function fix($q)
    {
        $a = explode(' ',$q);
        if(count($a) < 2) return $q;

        $r = array();
        foreach($a as $v)
        {
            if(strlen($v) > 2)
                $r[]=$v;
        }
        return join(' ',$r);
    }

    static public function execute()
    {
        $filterForm = sfConfig::get('formFilter');
        $cookie = sfConfig::get("cookie_search","cookie_search");
        $filters = Xtras::get($cookie);
        $form = new $filterForm($filters);
        return $form;
    }
}