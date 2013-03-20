<?php
namespace lagden;

use lagden\Utils as Utils;

// Depedencies symfony 1.4 libs
require_once(sfConfig::get('sf_symfony_lib_dir').'/helper/UrlHelper.php');

class Menu
{
    static public function dropdown($arr,$uri=null,$css="nav",$simple=false)
    {
        $close = false;
        $drop = ($css) ? '<ul class="'.$css.'">' : '<ul>';
        $sfUser = sfContext::getInstance()->getUser();
        foreach($arr as $k=>$v)
        {
            $exec = isset($v['credentials']) ? $sfUser->hasCredential($v['credentials']) : true;
            if( $exec )
            {
                $label = isset($v['label']) ? $v['label'] : false;
                $ac = isset($v['a_class']) ? $v['a_class'] : false;
                $c = isset($v['class']) ? $v['class'] : false;
                $a = ($v['route']) ? '<a href="'.url_for($v['route']).'" class="' . $ac . '">' . $label . '</a>' : '<a href="#act" class="' . $ac . '" data-toggle="dropdown">' . $label . '<b class="caret"></b></a>';
                $classes = static::match($a,$uri,$c);

                if($c == 'divider')
                {
                    $drop.='<li class="divider"></li>';
                }
                else
                {
                    $drop.='<li'.((count($classes)>0)?' class="'.join(' ',$classes).'"':'').'>'. $a ;
                }

                if(isset($v['children']) && !$simple)
                {
                    $drop.=static::dropdown($v['children'],$uri,'dropdown-menu');
                    $close=true;
                }
                else
                {
                    $drop.='</li>';
                    $close=false;
                }
                if($close) $drop.='</li>';
            }
        }
        $drop.='</ul>';
        return $drop;
    }

    static public function match($a,$uri,$c=false,$ca="active")
    {
        $classes = array();
        $regex= '/<a(.*)href=(\'|")([\?\=\-a-zA-Z-0-9_%\.:\/]*)/i';
        preg_match($regex,$a,$matches);
        if(isset($matches[3]) && $matches[3]==$uri) $classes[]=$ca;
        if($c) $classes[]=$c;
        return $classes;
    }

    // Frontend or Backend
    static public function yml($isFront=true)
    {
        $ds = DIRECTORY_SEPARATOR;
        $path = sfConfig::get('sf_data_dir');
        $file = "{$ds}" . (($isFront) ? "frontend" : "backend") . "{$ds}menu.yml";
        $menuContent = file_get_contents("{$path}{$file}");
        $menu = sfYaml::load($menuContent);
        return $menu;
    }

    static public function course($content, $dataValue='someValue', $data='tab', $href='#act', $class='curso_lnk')
    {
        sfContext::getInstance()->getConfiguration()->loadHelpers(array('Tag'));
        return content_tag("a","{$content}", array("class" => "{$class}", "data-{$data}" => $dataValue, "href"=>$href));
    }

    static public function header($arr,$uri=null,$css="menu",$handler=false)
    {
        sfContext::getInstance()->getConfiguration()->loadHelpers(array('Url'));
        $close = false;
        $drop = ($handler) ? '<div class="handler"></div>' : '';
        $drop .= ($css) ? '<ul class="'.$css.'">' : '<ul>';
        foreach($arr as $k=>$v)
        {
            $entraA = (isset($v['onlyfooter']) && $v['onlyfooter'] === false);
            $entraB = !isset($v['onlyfooter']);
            if($entraA || $entraB)
            {
                $label = isset($v['label']) ? $v['label'] : false;
                $ac = isset($v['a_class']) ? $v['a_class'] : false;
                $c = isset($v['class']) ? $v['class'] : false;
                $slug = (isset($v['slug'])) ? array("slug"=>$v['slug']) : array();
                $a = ($v['route']) ? '<a href="'.url_for($v['route'],$slug).'" class="' . $ac . '">' . $label . '</a>' : '<a href="#act" class="' . $ac . '">' . $label . '</a>';
                //$a = ($v['route']) ? '<a href="'.$v['route'].' - '.$v['slug'].'" class="' . $ac . '">' . $label . '</a>' : '<a href="#act" class="' . $ac . '">' . $label . '</a>';
                $classes = static::match($a,$uri,$c);

                if($c == 'divider')
                {
                    $drop.='<li class="divider"></li>';
                }
                else
                {
                    $drop.='<li'.((count($classes)>0)?' class="'.join(' ',$classes).'"':'').'>'. $a ;
                }

                if(isset($v['children']))
                {
                    $drop.=static::header($v['children'],$uri,'dropmenu',true);
                    $close=true;
                }
                else
                {
                    $drop.='</li>';
                    $close=false;
                }
                if($close) $drop.='</li>';
            }
        }
        $drop.='</ul>';
        return $drop;
    }

    // Helper para montar links
    static public function buildLink($v, $param, $extraParam = array(), $offset=null)
    {
        if(isset($param[$offset]) && $v->offsetExists($param[$offset]))
            return static::buildLink($v->get($param[$offset]), $param, $extraParam, $offset);
        else
        {
            $params = array('param'=>$param,'extraParam'=>$extraParam);
            if($v->offsetExists('target'))
            {
                $r = Utils::regexValidador($v->get('route'),'@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@');
                $r = ($r) ? 'param' : 'extraParam';
                $params[$r] = array_merge($params[$r], array('target'=>$v->get('target')));
            }
            return link_to($v->get('label'),$v->get('route'), $params['param'], $params['extraParam']);
        }
    }
}
