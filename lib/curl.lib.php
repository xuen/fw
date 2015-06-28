<?php 
/** Curl Wrapper (command or lib)
 *  
 *  
 *      
 */

function curl_config ($config) {
    Curl::Config($config);
}

function curl_backlog () {
    return Curl::Backlog();
}

function curl_header ($url, $options=array()) {
    return Curl::Header($url, $options);
}

function curl_get ($url, $data=array(), $options=array()) {
    return Curl::Get($url, $data, $options);
}

function curl_post ($url, $data=array(), $options=array()) {
    return Curl::Post($url, $data, $options);
}

function curl_download ($url, $path, $options=array()) {
    return Curl::Download($url, $path, $options);
}

function curl_info ($url, $options=array()) {
    return Curl::Info($url, $options);
}

class Curl {
    
    /**   log */
    private static $_Backlog = NULL ;

    /** Options name mapping which convert CURLOPT_XXX const to easy-read names */
    private static $_Options = array(
        /** 基本参数 */
        'url'       => 'CURLOPT_URL',
        'method'    => 'CURLOPT_CUSTOMREQUEST',
        'data'      => 'CURLOPT_POSTFIELDS', // array or string , file begin with '@'
        'ua'        => 'CURLOPT_USERAGENT',
        'timeout'   => 'CURLOPT_TIMEOUT',   // (secs) 0 means indefinitely
        'connect_timeout' => 'CURLOPT_CONNECTTIMEOUT' , // 
        'referer'   => 'CURLOPT_REFERER',
        'binary'    => 'CURLOPT_BINARYTRANSFER',
        'port'      => 'CURLOPT_PORT',
        /** 请求头信息 */
        'header'    => 'CURLOPT_HEADER', // TRUE:include header;
        'headers'   => 'CURLOPT_HTTPHEADER', // array
        /** 文件上传/下载 */
        'download'  => 'CURLOPT_FILE', // writing file stream (using fopen()), default is STDOUT
        'upload'    => 'CURLOPT_INFILE', // reading file stream
        /** other */
        'transfer'  => 'CURLOPT_RETURNTRANSFER', // TRUE:return string; FALSE:output directly (curl_exec)
        /** todo */
        // CURLOPT_USERPWD = user:password
        // CURLOPT_PROXY
        // CURLOPT_PROXYUSERPWD = user:password
        // CURLOPT_STDERR
        // CURLOPT_WRITEHEADER
        /** follow the redirect url (HTTP 301 302)*/
        'follow_location' => 'CURLOPT_FOLLOWLOCATION',
        /** ssl verifypeer */
        'ssl_verifypeer' => 'CURLOPT_SSL_VERIFYPEER',
    );
    
    /** 通用Curl处理过程 */
    private static function _Process ($url, $options=array()) {
        // 参数检查
        if(empty($url)) { 
            self::Backlog('Curl process url cannot be empty', 'error');
            return FALSE; 
        }
        // 设置选项默认值
        $urls = parse_url($url);
        if(isset($urls['port']) && $urls['port']!='80') $options['port'] = $urls['port'];
        $options = array_merge( array('header'=>0, 'transfer'=>1, 'url'=>$url, 'follow_location'=>1),  $options); 
        // 优化IP参数 (和传统使用不同，url使用域名，另外提供ip即可)
        if(!empty($options['ip'])) {
            // 提取主机名，加入头信息
            if ( empty($options['headers']) || !is_array($options['headers']) ) {
                $options['headers'] = array('Host: '.$urls['host']);
            }
            else {
                $options['headers'][] = 'Host: '.$urls['host'];
            }
            // 使用IP地址修改链接地址
            $options['url'] = str_replace($urls['host'], $options['ip'], $options['url']);
            unset($options['ip']);
        }
        // 优化HTTP协议版本参数
        if(!empty($options['http_version'])) {
            $version = $options['http_version'];
            if($version == '1.0') $options['CURLOPT_HTTP_VERSION'] = CURLOPT_HTTP_VERSION_1_0;
            elseif($version == '1.1') $options['CURLOPT_HTTP_VERSION'] = CURLOPT_HTTP_VERSION_1_1;
            unset($version);
        }
        // 优化info流程
        if(isset($options['return_info'])) {
            $return_info = $options['return_info']; unset($options['return_info']);
        }
        else {
            $return_info = FALSE;
        }
        // 映射参数(将自定义参数映射为系统CURLOPT_参数)，并保留至调试使用
        $opts = array();
        foreach($options as $key => $val) if(isset(self::$_Options[$key])) $opts[self::$_Options[$key]] = $val;
        // set params
        $options = array(); foreach($opts as $key => $val) $options[constant($key)] = $val;
        // execution
        $curl = curl_init(); curl_setopt_array($curl, $options); $result = curl_exec($curl); 
        if ($result===FALSE) { 
            self::Backlog('Curl exec error : '.curl_error($curl).' ('.curl_errno($curl).')', 'error');
            return $result;
        }
        // return info
        elseif($return_info) {
            $result = curl_getinfo($curl);
        }
        // return both header and body , you should use list($header, $body) to get result
        elseif($options[CURLOPT_HEADER]) {
            $result = explode("\r\n\r\n", $result, 2);
        }
        // close resource
        curl_close($curl);
        return $result ;
    }
    
    public static function Backlog ($msg = NULL, $type = 'error') {
        if(!$msg) return self::$_Backlog;
        $message = "{$type}:{$msg}";
        self::$_Backlog = $message;
        // todo : log
    }

    /** 获取头信息(暂时只支持GET，返回解析后的数组) */
    public static function Header ($url, $options=array()) {
        $options = array_merge(array('header'=>1, 'CURLOPT_NOBODY' => TRUE), $options);
        $header = self::_Process($url, $options);
        if(!$header) return $header;
        $header = trim($header); $header = explode("\n", $header); 
        $result = array();
        foreach($header as $h) {
            if(strpos($h, ':')) list($k, $v) = explode(':', $h);
            else list($k, $v) = array(0, $h);
            $result[$k] = trim($v);
        }
        return $result;
    }

    /** HTTP Get请求 */
    public static function Get ($url, $data=array(), $options=array()) {
        if(!empty($data)) { if(!strpos($url, '?')) $url .= '?'; $url .= http_build_query($data); }
        $opts = array_merge($options, array('method'=>'GET'));
        return self::_Process($url, $opts);
    }

    /** HTTP Post */
    public static function Post ($url, $data=array(), $options=array()) {
        $opts = array_merge($options, array('method'=>'POST', 'data'=>$data));
        return self::_Process($url, $opts);
    }
    
    /** (Multi) Download binary file
     *  @param  string|array    $url_or_urls
     *  @param  string  $file_or_dir
     *  @param  array   $options (no yet use)
     *  @return FALSE for failed / 1 for success
     */
    public static function Download ($url_or_urls, $file_or_dir, $options=array()) {
        // 
        if(is_array($url_or_urls) && is_array($file_or_dir) && count($url_or_urls)!=count($file_or_dir)) return FALSE;
        // valid parameters
        $urls = is_string($url_or_urls) ? array($url_or_urls) : $url_or_urls;
        $files = array();
        if(is_string($file_or_dir)) {
            $dir = is_dir($file_or_dir);
            foreach($urls as $url) {
                $files[] = $dir ? $dir.basename($url) : $file_or_dir;
            }
        }
        else {
            $files = $file_or_dir;
        }
        // multi download
        $result = 0;
        for($i=0,$len = count($urls);$i<$len;$i++) {
            $url = $urls[$i];
            $fp = fopen($files[$i], 'w+');
            $options = array( 'binary'=> 1, 'download'=> $fp );
            $r = self::_Process($url, $options);
            if($r!==FALSE) $result++;
            fclose($fp);
        }
        return $result;
    }

    /** 速度测试
     *  
     *  请求指定地址$url，返回时间信息
     *  
     */
    public static function Info ($url, $options=array()) {
        $options = array_merge(array('return_info'=>TRUE), $options);
        $info = self::_Process($url, $options);
        if(!$info) return $info;
        return array(
            'http_status' => $info['http_code'],
            'total_time' => $info['total_time'],
            'connect_time' => $info['connect_time'],
            'namelookup_time'=>$info['namelookup_time'],
            'pretransfer_time' => $info['pretransfer_time'],
            'starttransfer_time' => $info['starttransfer_time'],

            'upload_size' => $info['size_upload'],
            'download_size' => $info['size_download'],
            'upload_speed' => $info['speed_upload'],
            'download_speed' => $info['speed_download'],

        );
    }

}