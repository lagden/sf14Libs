<?php
namespace lagden;

use lagden\Utils as Utils;
use lagden\Xtras as Xtras;

// Depedencies symfony 1.4 libs
require_once(sfConfig::get('sf_symfony_lib_dir').'/helper/UrlHelper.php');

class GeneralActions extends sfActions
{
    // Listagem
    public function executeIndex(sfWebRequest $request)
    {
        // Setup App
        static::setup($request);
        $this->pager = Xtras::lista($request);
        $this->setTemplate('index', sfConfig::get("use_template","comum"));
    }

    public function executeClear(sfWebRequest $request)
    {
        // Setup App
        static::setup($request);
        // Limpa cookie do filtro
        $cookie = sfConfig::get("cookie_search", "cookie_search");
        Xtras::set(null, $cookie);
        $request->setParameter('pagina',1);
        $this->redirect(sfConfig::get('redirect_index'),sfConfig::get('params_route'));
    }

    public function executeFilter(sfWebRequest $request)
    {
        // Setup App
        static::setup($request);
        // Filtro
        $filterForm = sfConfig::get('formFilter');
        $filter = new $filterForm;
        $filterParams = $request->getParameter($filter->getName());
        // Seta cookie do filtro
        $cookie = sfConfig::get("cookie_search","cookie_search");
        Xtras::set($filterParams, $cookie);
        // Seta a pagina para 1
        $request->setParameter('pagina',1);
        $this->redirect(sfConfig::get('redirect_index'),sfConfig::get('params_route'));
    }

    public function executeSort(sfWebRequest $request)
    {
        // Setup App
        static::setup($request);
        $this->getResponse()->setContentType('text/plain');
        $response=Xtras::sort($request);
        if(is_string($response)&&$response=="reload")return $this->renderText($response);
        else
        {
            return $this->renderPartial(sfConfig::get("tbody",'global/tbody'),
            array(
                'fields' => array('labels'=>sfConfig::get('fields_labels',array()),'names'=>sfConfig::get('fields_names',array()),'sorts'=>sfConfig::get('fields_sorts',array()),'xtras'=>sfConfig::get('fields_xtras',array()),),
                'actions' => array('edit'=>sfConfig::get('action_edit'),'delete'=>sfConfig::get('action_delete'),'new'=>sfConfig::get('action_new'),),
                'itens' => $response->getResults(),
                'lastId' => $this->getUser()->getAttribute(sfConfig::get('last_edited')),
            ));
        }
    }
    
    public function executeReposition(sfWebRequest $request)
    {
        // Setup App
        static::setup($request);
        $this->getResponse()->setContentType('application/json');
        $response = Xtras::reposition($request);
        return $this->renderText(json_encode($response));
    }

    /* CRUD
    ---------------------------------------------------------------------------------*/
    public function executeNew(sfWebRequest $request)
    {
        self::newOrEdit($request,true);
        $this->setTemplate('new', sfConfig::get("use_template","comum"));
    }

    public function executeEdit(sfWebRequest $request)
    {
        self::newOrEdit($request);
        $this->setTemplate('edit', sfConfig::get("use_template","comum"));
    }

    public function executeCreate(sfWebRequest $request)
    {
        self::createOrUpdate($request,true);
    }

    public function executeUpdate(sfWebRequest $request)
    {
        self::createOrUpdate($request);
    }

    protected function newOrEdit(sfWebRequest $request, $is_new=false)
    {
        // Setup App
        static::setup($request);
        // Referer
        Xtras::referer();
        $formModel = sfConfig::get('form_model');
        if($is_new)
        {
            sfConfig::set('section',sfConfig::get('title_new'));
            $this->form = new $formModel();
        }
        else
        {
            sfConfig::set('section',sfConfig::get('title_edit'));
            $obj = Doctrine_Core::getTable(sfConfig::get('table_model'))->find($request['id']);
            $this->forward404Unless('Objeto não encontrado');
            // Ultimo Editado
            $this->getUser()->setAttribute(sfConfig::get('last_edited'), $request['id']);
            $this->form = new $formModel($obj);
        }
    }

    protected function createOrUpdate(sfWebRequest $request, $is_new=false)
    {
        // Setup App
        static::setup($request);
        $formModel=sfConfig::get('form_model');
        if($is_new)
        {
            sfConfig::set('section',sfConfig::get('title_new'));
            $template='new';
            $this->forward404Unless($request->isMethod(sfRequest::POST));
            $this->form = new $formModel();
        }
        else
        {
            sfConfig::set('section',sfConfig::get('title_edit'));
            $template='edit';
            $this->forward404Unless($request->isMethod(sfRequest::POST) || $request->isMethod(sfRequest::PUT),'Os métodos permitidos são POST ou PUT.');
            $obj = Doctrine_Core::getTable(sfConfig::get('table_model'))->find($request['id']);
            $this->forward404Unless($obj,'Objeto não encontrado');
            $this->form = new $formModel($obj);
        }
        self::processForm($request, $this->form);
        $this->setTemplate($template, sfConfig::get("use_template","comum"));
    }

    private function processForm(sfWebRequest $request, sfForm $form)
    {
        $post = $request->getParameter($form->getName());
        
        $form->bind($post, $request->getFiles($form->getName()));
        if ($form->isValid())
        {
            $obj = $form->save();
            if($obj)
            {
                $notice = 'Dados gravados com sucesso.';
                $this->getUser()->setFlash('notice', "{$notice}", true);
                $this->getUser()->setAttribute(sfConfig::get('last_edited'), $obj->id);
                $saveEdit=$request->getCookie('salvaEdita',false);
                if($saveEdit)
                {
                    sfContext::getInstance()->getResponse()->setCookie('salvaEdita',null);
                    $this->redirect(sfConfig::get('action_edit'),array_merge(sfConfig::get('params_route'),array('id' => $obj->id)));
                }
                else $this->redirect(sfConfig::get('redirect_index'),sfConfig::get('params_route'));
            }
            else
            {
                $notice = 'Falha ao gravar.';
                die($notice);
                $this->getUser()->setFlash('notice', "{$notice}", true);
            }
        }
        else
        {
            $notice = 'Formulário inválido.';
            $this->getUser()->setFlash('notice', "{$notice}", true);

            // Debug
            // $errors=array();
            // foreach($form as $k=>$v)$errors[$k]=$form[$k]->renderError();
            // Utils::trace($form->renderGlobalErrors(),$errors);
            // die;
        }
    }

    /* Ajax Delete
    ---------------------------------------------------------------------------------*/
    public function executeDelete(sfWebRequest $request)
    {
        // Setup App
        static::setup($request);
        $this->getResponse()->setContentType('application/json');
        return $this->renderText(Xtras::delete($request));
    }

    /* End CRUD
    ---------------------------------------------------------------------------------*/

    static public function setup($request=null)
    {
        // Code...
    }

    static public function setupDefault($request, $prefix, $single, $plural, $sort_field = "id", $sort_direction = "DESC", $draggable=false)
    {
        
        // Vars
        $prefix_uc = ucfirst($prefix);
        $single_uc = ucfirst($single);
        $plural_uc = ucfirst($plural);

        // Names
        sfConfig::set("v_single",$single);
        sfConfig::set("v_single_uc",$single_uc);
        sfConfig::set("v_plural",$plural);
        sfConfig::set("v_plural_uc",$plural_uc);

        // Filtro
        sfConfig::set("formFilter","SearchFormFilter"); // Formulario que sera utilizado no filtro
        sfConfig::set("cookie_search","{$prefix}_search.filters"); // cookie do filtro
        sfConfig::set("component_class","comum"); // Nome do componente que tem o Filter
        sfConfig::set("route_form_filter","{$prefix}_filter"); // Rota de submit do filtro
        sfConfig::set("route_form_filter_reset","{$prefix}_clear"); // Rota para limpar o filtro
        sfConfig::set("field_form_filter","q"); // Campo do filtro

        // Títulos
        sfConfig::set("title_list","Lista de {$plural}"); // Lista
        sfConfig::set("title_edit","Editar {$single}"); // Edita
        sfConfig::set("title_new","Add {$single}"); // Add

        //Modelo da tabela
        sfConfig::set("table_model","{$prefix_uc}"); // Table Model Class

        // Rotas
        sfConfig::set("redirect_index","{$prefix}"); // Rota do index
        sfConfig::set("page_route","{$prefix}_page"); // Rota para paginacao

        // Cookie
        sfConfig::set("last_edited","{$prefix}_last.edited");

        // Action List
        sfConfig::set("action_create","{$prefix}_create");
        sfConfig::set("action_update","{$prefix}_update");
        sfConfig::set("action_edit","{$prefix}_edit");
        sfConfig::set("action_delete","{$prefix}_delete");
        sfConfig::set("action_new","{$prefix}_new");
        sfConfig::set("action_sort","{$prefix}_sort");
        sfConfig::set("action_reposition","{$prefix}_reposition");

        // Params
        $params = array();
        sfConfig::set("params_route",$params); // Parametros para rota

        // Sort Table
        sfConfig::set("order_by","{$prefix}_sort.field");
        sfConfig::set("order_by_default","{$sort_field}");
        sfConfig::set("order_by_direction","{$prefix}_sort.direction");
        sfConfig::set("order_by_direction_default","{$sort_direction}"); 

        // Table Drag
        sfConfig::set("table_drag", (($draggable) ? "dragREDIPS" : false));

        // Form
        sfConfig::set("form_model","{$prefix_uc}Form");
        sfConfig::set("form_id","{$prefix}Form");
        sfConfig::set("ignores",array('id','_csrf_token'));

        // Fields Searchable
        $fields=array("id");
        sfContext::getInstance()->getUser()->setAttribute("search_list.fields", $fields);

        // Table List
        sfConfig::set("fields_labels",array("Ação"));
        sfConfig::set("fields_names",array("id"));
        sfConfig::set("fields_sorts",array(false));
    }

    // Galeria Stuff
    // Faz Upload das Imagens
    public function executeUploading(sfWebRequest $request)
    {
        // Galeria
        $g = static::galeriaInfo();
        $field_id = $g['foreignAliasFieldId'];
        $chunkForm = $g['chunkForm'];
        $theForm = $g['theForm'];
        $formField = $g['formField'];
        $output = $g['foreignAliasField'];

        $this->getResponse()->setContentType('application/json');

        $id = $request->getPostParameter('id',null);
        $name = $request->getPostParameter('name',null);
        $rnd = $request->getPostParameter('rnd',null);

        $chunk = $request->getPostParameter('chunk',null);
        $chunks = $request->getPostParameter('chunks',null);

        if($chunks > 1)
        {
            $filename = "{$id}_{$rnd}_{$name}";
            $clean=false;
            if(isset($_FILES[$formField]['tmp_name']) && is_uploaded_file($_FILES[$formField]['tmp_name']))
            {
                $tmp = $_FILES[$formField]['tmp_name'];
                $clean = true;
            }
            else
                $tmp = "php://input";

            $in = fopen($tmp, "rb");
            $out = fopen(sfConfig::get('sf_upload_dir') . DIRECTORY_SEPARATOR . $filename, $chunk == 0 ? "wb" : "ab");
            if(!$out) return $this->renderText('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}}');
            if(!$in) return $this->renderText('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}}');
            while ($buff = fread($in, 4096)) fwrite($out, $buff);
            fclose($in);
            fclose($out);
            if($clean) @unlink($_FILES[$formField]['tmp_name']);
            if($chunks==($chunk+1))
            {
                $form = new $chunkForm();
                $form->bind(array($field_id=>$id, $formField=>$filename));
            }
            else return $this->renderText(json_encode(array($chunks=>$chunk+1)));
        }
        else
        {
            $form = new $theForm();
            $form->bind(array($field_id=>$id),$request->getFiles());
        }

        $response=array(
            'jsonrpc'=>'2.0',
            'error'=>array(
                'code'=>'103',
                'message'=>'Failed to move uploaded file.',
            ),
            'success' => false,
            'auth' => true,
            'data' => null,
        );

        if ($form->isValid())
        {
            try
            {
                $image = $form->save();
                // Response
                unset($response['error']);
                $response['success']=true;
                $response['data']=array('file'=>$image->$output,'id'=>$image->id);
                return $this->renderText(json_encode($response));
            }
            catch (Exception $e)
            {
                $response['error']['code']='105';
                $response['error']['message']="Failed to save on database.\n{$e->getMessage()}";
                return $this->renderText(json_encode($response));
            }
        }
        return $this->renderText(json_encode($response));
    }

    // Galeria Stuff
    // Ajax Add File
    public function executeAddFile(sfWebRequest $request)
    {
        // Galeria
        $g = static::galeriaInfo();

        // Verifica a sessão
        $auth = $this->getUser()->isAuthenticated();
        if(!$auth) return $this->renderText("end_of_session");

        $merge = array_merge($g['partialParam'], array('id'=>$request['id'],'file'=>$request['file']));
        return $this->renderText(Utils::get_partial($g['partial'],$merge,$g['partialSub']));
    }

    // Galeria Stuff
    // Ajax Remove File
    public function executeRemoveFile(sfWebRequest $request)
    {
        $g = static::galeriaInfo();

        $this->getResponse()->setContentType('application/json');

        // Response
        $response = Utils::response();

        // Verifica a sessão
        $auth = $this->getUser()->isAuthenticated();
        if(!$auth){
            $response['auth']=false;
            $response['msg']="Sessão expirada. Efetue o login novamente.";
            return $this->renderText(json_encode($response));
        }

        // Verificando Method
        if($request->isMethod(sfRequest::POST) || $request->isMethod(sfRequest::DELETE))
            $result = Doctrine_Core::getTable($g['tabela'])->find($request['id']);
        else
            $response['msg']="Os métodos permitidos são: POST ou DELETE.";

        // Do it
        if( $result )
        {
            $result->delete();
            $response['success']=true;
            $response['msg']="O arquivo foi removido com sucesso.";
            $response['data']=array('id'=>$request['id']);
        }
        else
        {
            $response['msg']="Arquivo não encontrado.";
        }
        return $this->renderText(json_encode($response));
    }
}
