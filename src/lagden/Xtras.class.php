<?php
namespace lagden;

use lagden\Utils as Utils;
use lagden\FileCache as FileCache;
// Depedencies symfony 1.4 libs

/**
 * Xtras class
 *
 * @package default
 * @author Thiago Lagden
 **/
class Xtras
{
    // Get
    static public function get($name='default_data', $default=array())
    {
        return sfContext::getInstance()->getUser()->getAttribute($name, $default);
    }

    // Set
    static public function set($filters,$name='default_data')
    {
        return sfContext::getInstance()->getUser()->setAttribute($name, $filters);
    }

    // Return array with necessary keys for pager works
    static public function getPagerCache($query, $entity, $request, $maxPerPage, $extraHash = null)
    {
        $pager = static::getPager($query, $entity, $request, $maxPerPage);
        
        $dirCache = Doctrine_Core::getTable($entity)->getCacheName();
        FileCache::setInstance($dirCache);

        $hash = md5($pager->getQuery()->getDql() . $extraHash);
        $cache = FileCache::getCache($hash);

        if(!$cache)
        {
            $cache = array();
            $cache['haveToPaginate'] = $pager->haveToPaginate();
            $cache['getPage'] = $pager->getPage();
            $cache['getLastPage'] = $pager->getLastPage();
            $cache['getResults'] = $pager->getResults()->toArray();
            $cache['count'] = $pager->count();
            FileCache::setCache($hash, $cache, 600000); // 10 minutos
        }
        return $cache;
    }

    // Return sfDoctrinePager
    static public function getPager($query, $entity, $request, $maxPerPage)
    {
        $pagina = $request->getParameter('pagina',1);
        $pager = new sfDoctrinePager($entity);
        $pager->setQuery($query);
        $pager->setPage($pagina);
        $pager->setMaxPerPage($maxPerPage);
        $pager->init();
        return $pager;
    }

    // Return Zend_Paginator
    static public function getZendPager($result, $request, $maxPerPage)
    {
        ProjectConfiguration::registerZend();
        $pagina = $request->getParameter('pagina',1);
        $pager = Zend_Paginator::factory($result);
        $pager->setCurrentPageNumber($pagina);
        $pager->setItemCountPerPage($maxPerPage);
        return $pager;
    }

    // Return String
    static public function getSearchTerm($name='name_search_term', $field='q')
    {
        $term = static::get($name, array("{$field}" => ''));
        $term = isset($term[$field]) ? $term[$field] : '';
        return $term;
    }

    // Return @if true {Doctrine_Query} else {Empty Array}
    static public function getQuery($tbl, $term, $culture = null)
    {
        $q = $tbl::getListQuery();
        $alias = $q->getRootAlias();

        if($tbl->hasRelation('Translation'))
        {
            $q->leftJoin("{$alias}.Translation t")->andWhere('t.lang = ?', $culture);
            $q = SearchLucene::getLuceneQuery($tbl, $term, $culture, $q);
        }
        else
            $q = $tbl->getLuceneQuery($term, $q);
        
        return $q;
    }

    // Retorn Array - @if true {query and pks} else {empty}
    static public function getLuceneQueryAndPks($tbl, $term, $culture = null)
    {
        $q = $tbl->getListQuery();
        $alias = $q->getRootAlias();

        if($tbl->hasRelation('Translation'))
            $q->leftJoin("{$alias}.Translation t")->andWhere('t.lang = ?', $culture);
        else
            $culture = null;

        return SearchLucene::getLuceneQueryAndPks($tbl, $term, $culture, $q);
    }

    // CRUD - Listagem
    public static function lista($request)
    {
        // Referer
        static::referer();
        // Title
        sfConfig::set('section',sfConfig::get('title_list'));
        // Query
        $query = static::getFilterQuery();
        // Pagination
        return static::getPager($query, sfConfig::get('table_model'), $request, sfConfig::get('app_search_max_per_page'));
    }

    // Sortable Ajax - Return HTML
    public static function sort($request)
    {
        // Verifica a sessão
        $auth = sfContext::getInstance()->getUser()->isAuthenticated();
        if(!$auth) return "reload";

        sfContext::getInstance()->getUser()->setAttribute(sfConfig::get('order_by'), $request->getParameter('field',sfConfig::get('order_by_default','id')));
        sfContext::getInstance()->getUser()->setAttribute(sfConfig::get('order_by_direction'), $request->getParameter('direction',sfConfig::get('order_by_direction_default','DESC')));

        // Query
        $query = static::getFilterQuery();
        $pager = static::getPager($query, sfConfig::get('table_model'), $request, sfConfig::get('app_search_max_per_page'));
        return $pager;
    }

    static public function getFilterQuery($cookie='cookie_search', $entity=null)
    {
        // Valores
        $values=static::get($cookie);

        // Query
        $entity = sfConfig::get('table_model',$entity);
        $tbl = Doctrine_Core::getTable($entity);
        $q = ($values) ? $tbl->getListFilter($values) : $tbl->getListFilter();
        
        return $q;
    }

    // Reposition Ajax - Return JSON
    public static function reposition($request)
    {
        $response = Utils::response();

        $pagina = $request->getParameter('pagina',1);

        // Verifica a sessão
        $auth = sfContext::getInstance()->getUser()->isAuthenticated();
        if(!$auth)
        {
            $response['auth']=false;
            return $response;
        }

        $post = $request->getParameter('post',false);
        if(is_array($post))
        {
            $tblCore = Doctrine_Core::getTable(sfConfig::get('table_model'));
            foreach($post as $p)
            {
                $item = $tblCore->find(intval($p['id']));
                if($item)
                {
                    $num = static::generateNum($item, $pagina, $p['pos']);
                    $item->moveToPosition(intval($num));
                    $item->free(true);
                    $response['success']=true;
                }
            }
        }

        if($response['success'])$response['msg']='Dados reposicionados.';
        else $response['msg']='Nenhum dado foi alterado ou reposicionado.';

        return $response;
    }

    static private function generateNum($i,$pagina,$pos)
    {
        $current = "{$pagina}{$pos}";

        $tbl = $i->getTable();
        return $current;
    }

    // CRUD - Delete Ajax - Return JSON
    public static function delete($request)
    {
        // Response
        $response=array(
            'success' => false,
            'auth' => true,
            'msg' => 'Erro',
            'data' => null,
        );

        // Verifica a sessão
        $auth = sfContext::getInstance()->getUser()->isAuthenticated();
        if(!$auth)
        {
            $response['auth']=false;
            $response['msg']="Sessão expirada. Efetue o login novamente.";
            return json_encode($response);
        }

        // Somente Admin
        $admin = sfContext::getInstance()->getUser()->hasCredential('administrador');

        // Verificando Method
        if($request->isMethod(sfRequest::POST) || $request->isMethod(sfRequest::DELETE))
        {
            $result = Doctrine_Core::getTable(sfConfig::get('table_model'))->find($request['id']);
        }
        else
        {
            $response['msg']="Os métodos permitidos são: POST ou DELETE.";
        }

        // Do it
        if( $result && $admin )
        {
            try
            {
                if($result->getNode()) $result->getNode()->delete();
                else $result->delete();

                $response['success']=true;
                $response['msg']="O registro foi removido com sucesso.";
                $response['data']=array('id'=>$request['id']);
                sfContext::getInstance()->getUser()->setAttribute(sfConfig::get('last_edited'), null);
                return json_encode($response);
            }
            catch (Exception $e)
            {
                $response['msg']="Não foi possível remover o registro.";
                return json_encode($response);
            }
        }
        else
        {
            $response['msg']="Registro não encontrado.";
            return json_encode($response);
        }
        return json_encode($response);
    }

    // Referer for session expired
    static public function referer()
    {
        sfContext::getInstance()->getUser()->setAttribute('referer',$_SERVER['PHP_SELF']);
        return null;
    }

    // Retorna sfAction
    static public function getAction()
    {
        $instance = sfContext::getInstance();
        return $instance->getController()->getAction( $instance->getModuleName(), $instance->getActionName() );
    }

    // ClearCache
    static public function clearCache($r)
    {
        FileCache::setInstance($r);
        FileCache::cleanCache();
    }

    static public function metas($r)
    {
        FileCache::setInstance("mySectionCache");
        $nameCache = md5("section.{$r}.array");
        $cache = FileCache::getCache($nameCache);
        if(!$cache)
        {
            $cache = Doctrine_Core::getTable('Section')->getOneByRouteWithJoins($r)->fetchOne();
            $cache = ($cache) ? $cache->toArray() : array();
            FileCache::setCache($nameCache, $cache);
        }
        $section = Utils::array2object($cache);
        if($section)
        {
            static::setHttpMeta($section,'name','app_title','description',true);
            return $section;
        }
        return false;
    }

    static public function setHttpMeta($o,$n=false,$nc=false,$d=false,$t=false)
    {
        $r = sfContext::getInstance()->getResponse();
        $nc = sfConfig::get($nc,false);
        if($n) $r->addMeta('title', "{$o->$n}" . (($nc) ? " - {$nc}" : '' ), true, true);
        if($d) $r->addMeta('description', "{$o->$d}", true, true);
        if($t)
        {
            if(isset($o->Tags) && count($o->Tags) > 0)
            {
                $r->addMeta('keywords', Utils::getJoin($o->Tags), true, true);    
            }
        }
    }

    static public function linkMedia($t, $rel, $tbl, $title = null, $descricao = null)
    {
        $title = $title ? $title : $t->get('nome');
        $descricao = $descricao ? $descricao : $title;
        $r = "";
        switch ($t->get('midia')) {
            case 'imagem':
                $href = public_path("/uploads/{$tbl}/{$t->get('arquivo')}");
                $r = '<a class="fancybox-media" rel="'.$rel.'" title="'.$title.'" href="'.$href.'">'.$descricao.'</a>';
                break;
            case 'video':
                $href = $t->get('link');
                $r = '<a class="fancybox-media fancybox.iframe" rel="'.$rel.'" title="'.$title.'" href="'.$href.'">'.$descricao.'</a>';
                break;
            case 'audio':
                $href = $t->get('audio');
                $r = '<a class="fancybox-media fancybox.iframe" rel="'.$rel.'" title="'.$title.'" href="https://w.soundcloud.com/player/?color=990000&show_artwork=true&url='.$href.'">'.$descricao.'</a>';
                break;
            default:
                $href = public_path("/uploads/{$tbl}/{$t->get('arquivo')}");
                $r = '<a download="'.$t->get('slug').'" href="'.$href.'">'.$descricao.'</a>';
                break;
        }
        return $r;
    }
}