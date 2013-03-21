<?php
namespace lagden;
use \DateTime as DateTime;

class Utils
{
    /**
    *
    * @author Thiago Lagden
    */

    // Cria um arquivo 
    static public function log($content, $file, $do=FILE_APPEND)
    {
        file_put_contents($file,$content,$do);
    }

    // Formata datas
    static public function date($date,$format='d/m/Y')
    {
        if($date)
        {
            try
            {
                $d = new DateTime($date);
                return $d->format($format);
            }
            catch (Exception $e)
            {
                return null;
            }
        }
        else return null;
    }

    // Validação com expressão regular
    static public function regexValidador($str, $pattern)
    {
        return preg_match($pattern, $str);
    }

    // Validação de email
    static public function emailValidador($str)
    {
        return static::regexValidador($str,'/^([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i');
    }

    // diferença de datas timestamp
    static public function diff($date1,$date2="now")
    {
        $d1 = strtotime($date1);
        $d2 = strtotime($date2);
        return $d1 - $d2;
    }

    // de 31/12/2007 | 31-12-2007 para 2007-12-31
    static public function toMysql($date)
    {
        preg_match('/^(0[1-9]|[12][0-9]|3[01])[- \/\.](0[1-9]|1[012])[- \/\.](\d{4})$/', $date, $matches);
        if(count($matches))
        {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }
        return null;
    }

    // join na lista de objetos
    static public function getJoin($es,$f='name')
    {
        $arr = array();
        foreach ($es as $e) {
            $arr[]=$e->$f;
        }
        return join(', ',$arr);
    }

    // mes em pt_br
    static public function magicMes($get=false)
    {
        $mes=array();
        $mes[0]='';
        $mes[1]='Janeiro';
        $mes[2]='Fevereiro';
        $mes[3]='Março';
        $mes[4]='Abril';
        $mes[5]='Maio';
        $mes[6]='Junho';
        $mes[7]='Julho';
        $mes[8]='Agosto';
        $mes[9]='Setembro';
        $mes[10]='Outubro';
        $mes[11]='Novembro';
        $mes[12]='Dezembro';

        $intGet = intval($get);
        
        if ($intGet) return (isset($mes[$intGet])) ? $mes[$intGet] : null;
        else return $mes;
    }

    // retorna o periodo de um time | datetime
    static public function periodo($v)
    {
        $hora = static::date($v,"H");
        if($hora >= 0 && $hora < 12) return "Manhã";
        if($hora >= 12 && $hora < 18) return "Tarde";
        if($hora >= 18 && $hora < 23) return "Noite";
        return false;
    }

    // de file.xxx para file.098123098.xxx
    static public function noCacheFile($file)
    {
        $r = pathinfo($file);
        $ext = (isset($r['extension'])) ? $r['extension'] : null;
        return ($ext) ? str_replace(".{$ext}", "." . mt_rand() . ".{$ext}", $file) : $file;
    }

    // negócio inutil
    static public function array2object(Array $a)
    {
        return json_decode(json_encode($a));
    }

    static public function soundcloudGetTrack($v, $client_id='152edd06f09770884c5e611f13ee0598')
    {
        $uri = null;
        $JSON = file_get_contents("http://api.soundcloud.com/resolve.json?url={$v}&client_id={$client_id}");
        if($JSON)
        {
            $JSON_Data = json_decode($JSON);
            $uri = $JSON_Data->uri;
        }
        return $uri;
    }

    // pega o id dos videos do vimeo e youtube
    static public function parseVideoURL($url)
    {
        preg_match('/((?<=v(\=|\/))|(?<=embed(\=|\/)))([-a-zA-Z0-9_]+)|((?<=vimeo\.com\/)(\d+)|(?<=youtu\.be\/)([-a-zA-Z0-9_]+))/', $url, $matches);
        if(count($matches))
        {
            return "{$matches[0]}";
        }
        return null;
    }

    // pega id do video do vimeo
    static public function vimeoId($url)
    {
        return static::parseVideoURL($url);
    }

    // pega informações do video do vimeo
    static public function vimeoInfo($v)
    {
        $JSON = file_get_contents("http://vimeo.com/api/v2/video/{$v}.json");
        $JSON_Data = json_decode($JSON);
        return $JSON_Data;
    }

    // pega id do video do youtube
    static public function ytVideoId($url)
    {
        return static::parseVideoURL($url);
    }

    // pega informações do video do youtube
    static public function ytVideoInfo($v)
    {
        $JSON = file_get_contents("https://gdata.youtube.com/feeds/api/videos/{$v}?alt=json&v=2");
        $JSON_Data = json_decode($JSON);
        return $JSON_Data->{'entry'};
    }

    // Flickr stuff 1
    static public function flickrInfo($user_id = "35972250@N06", $per_page = 9, $api_key = "74d183b502ced5f6f83e96d312f37db6")
    {
        $JSON = file_get_contents("http://api.flickr.com/services/rest/?&method=flickr.people.getPublicPhotos&api_key={$api_key}&user_id={$user_id}&per_page={$per_page}&format=json&nojsoncallback=1");
        $JSON_Data = json_decode($JSON);
        return $JSON_Data->photos->photo;
    }

    // Flickr stuff 2
    static public function flickrPhotoSetInfo($photoset_id = "72157630196492872", $per_page = 9, $api_key = "74d183b502ced5f6f83e96d312f37db6")
    {
        $JSON = file_get_contents("http://api.flickr.com/services/rest/?&method=flickr.photosets.getPhotos&api_key={$api_key}&photoset_id={$photoset_id}&per_page={$per_page}&format=json&nojsoncallback=1");
        $JSON_Data = json_decode($JSON);
        return $JSON_Data->photoset->photo;
    }

    // Default response Ajax Json
    static public function response()
    {
        return array(
            'success' => false,
            'auth' => true,
            'msg' => 'Erro',
            'data' => null,
        );
    }

    static public function buildUrl($route,$params,$merge=false)
    {
        if(is_object($merge))$result = array_merge($params, $merge->getRawValue());
        elseif(is_array($merge))$result = array_merge($params, $merge);
        else $result=$params;
        return url_for($route,$result);
    }

    static public function getFirstItem($arr)
    {
        if(!empty($arr))
            return isset($arr[0]) ? $arr[0] : null;
        return null;
    }

    static public function stringIsNullOrEmpty($str)
    {
        return (!isset($str) || strlen($str)===0);
    }
}