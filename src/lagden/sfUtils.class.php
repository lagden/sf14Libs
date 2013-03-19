<?php
namespace lagden;

// Depedencies symfony 1.4 libs
require_once(sfConfig::get('sf_symfony_lib_dir').'/helper/UrlHelper.php');

class sfUtils
{
    /**
    *
    * @author Thiago Lagden
    */

    // retorna um rota valida
    static public function buildUrl($route,$params,$merge=false)
    {
        if(is_object($merge))
            $result = array_merge($params, $merge->getRawValue());
        elseif(is_array($merge))
            $result = array_merge($params, $merge);
        else
            $result=$params;

        return url_for($route,$result);
    }

    // parte de um template
    static public function get_partial($templateName, $vars = array(), $theFolder = null)
    {
        $context = sfContext::getInstance();

        // partial is in another module?
        if (false !== $sep = strpos($templateName, '/'))
        {
            $moduleName   = substr($templateName, 0, $sep);
            $templateName = substr($templateName, $sep + 1);
        }
        else
            $moduleName = $context->getActionStack()->getLastEntry()->getModuleName();

        $actionName = (($theFolder) ? $theFolder . DIRECTORY_SEPARATOR : "") . '_'.$templateName;

        $class = sfConfig::get('mod_'.strtolower($moduleName).'_partial_view_class', 'sf').'PartialView';
        $view = new $class($context, $moduleName, $actionName, '');
        $view->setPartialVars(true === sfConfig::get('sf_escaping_strategy') ? sfOutputEscaper::unescape($vars) : $vars);

        return $view->render();
    }
}