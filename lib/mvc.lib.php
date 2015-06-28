<?php
/**   A lightweight MVC framework 
 *  
 *     @usage
 *     
 *     try {
 *         MVC::Config(array( 'path' => '/path/to/mvc', 'routes' => array()));
 *         MVC::Dispatch($_SERVER['REQUEST_URI']);
 *     }
 *     catch(MVCException $ex) {
 *         MVC::Capture($ex);
 *     }
 *  ));
 * 
 */
class MVC {

    /**   Exception Code */
    public static $Code = array(
        // config errors 1x
        'CONFIG_EMPTY' => 11,
        'CONFIG_PATH_EMPTY_OR_NOT_EXISTS' => 12,
        'CONFIG_ROUTE_EMPTY_OR_INVALIDE' => 13,

        // route errors 2x
        'ROUTE_NOT_MATCHED' => 21, 
        'CONTROLLER_NOT_MATCHED' => 22,
        'ACTION_NOT_MATCHED' => 23,
        'CONTROLLER_NOT_FOUND' => 24,
        'CONTROLLER_NOT_DEFINED' => 25,
        'VIEW_NOT_FOUND' => 26,
        'PARAM_NOT_MATCHED' => 27,
        'MODEL_NOT_FOUND' => 28,
        'MODEL_NOT_DEFINED' => 29,

    );

    /**   Base path of MVC code */
    public static $Path ;

    /**   Model DB */
    public static $DB ;

    /**   Config of MVC settings */ 
    public static $Config = array(
        // url routes
        'routes' => array(
            '^/?$' => array('controller'=>'home', 'action'=>'index'),
            '^/([0-9a-zA-Z]+)/?$' => array('controller'=>'$1', 'action'=>'index'),
            '^/([0-9a-zA-Z]+)/([0-9a-zA-Z]+)/?' => array('controller'=>'$1', 'action'=>'$2'),
        )
    );
    
    public static function Config ($config) {
        
        // check config
        if(empty($config)) 
            throw new MVCException('Config cannot be empty', self::$Code['CONFIG_EMPTY']);
        if(empty($config['path'])) 
            throw new MVCException('Config path cannot be empty', self::$Code['CONFIG_PATH_EMPTY_OR_NOT_EXISTS']);
        
        // check path and fix with separator
        $path = $config['path'];
        if(!file_exists($path) || !is_dir($path)) 
            throw new MVCException('Config path not exists', self::$Code['CONFIG_PATH_EMPTY_OR_NOT_EXISTS']);
        if(substr($path,-1)!=DIRECTORY_SEPARATOR) $path .= DIRECTORY_SEPARATOR;
        self::$Path = $path;

        // merge config
        self::$Config = array_merge(self::$Config, $config);
        
        // check routes
        if(empty(self::$Config['routes'])) 
            throw new MVCException('Config routes cannot be empty', self::$Code['CONFIG_ROUTE_EMPTY_OR_INVALIDE']);
        foreach(self::$Config['routes'] as $route)
            if(empty($route['controller']) || empty($route['action'])) 
                throw new MVCException('Config routes is invalide', self::$Code['CONFIG_ROUTE_EMPTY_OR_INVALIDE']);    
        
        // model db
        if(isset(self::$Config['model']['db'])) self::$DB = self::$Config['model']['db'];

        // registor autoload
        spl_autoload_register('MVC::Autoload');
    }

    /**   Dispatch routes */
    public static function Dispatch ($url) {
        // only path (no ?)
        $pos = strpos($url,'?'); 
        if($pos!==FALSE) $url = substr($url, 0, $pos); 
        // auto add '/' beign
        // if(substr($url,0,1)!='/') $url = '/'.$url;
        // auto add '/' end
        // if(substr($url,-1)!='/') $url .= '/';
        $urls = explode('/', trim($url, '/'));

        // init data
        $controller = $action = $params = $route = $error = FALSE;

        // routes rules (first)
        $routes = self::$Config['routes'];
        foreach ($routes as $key => $val) {
            if (preg_match('@'.str_replace('/','\/',$key).'@', $url, $matches)) {
                $route = $val; break;
            }
        }
        if(!$route) 
            throw new MVCException('Route not matched', self::$Code['ROUTE_NOT_MATCHED']);

        $controller = $route['controller']; unset($route['controller']);
        if($controller[0]=='$') {
            $controller = intval(substr($controller,1));
            if(!isset($matches[$controller])) 
                throw new MVCException('Controller not matched : '.$controller, self::$Code['CONTROLLER_NOT_MATCHED']);
            $controller = $matches[$controller];
        }

        $action = $route['action']; unset($route['action']);
        if($action[0]=='$') {
            $action = intval(substr($action,1));
            if(!isset($matches[$action])) 
                throw new MVCException('Action not matched : ', self::$Code['ACTION_NOT_MATCHED']);
            $action = $matches[$action];
        }
        
        $params = array();
        if(!empty($route)) {
            foreach($route as $key => $val) {
                if($val[0]=='$') {
                    $val = intval(substr($val,1));
                    if(!isset($matches[$val])) throw new MVCException('Param not matched : ', self::$Code['PARAM_NOT_MATCHED']);
                    $val = $matches[$val];
                }
                $params[$key] = $val;
            }
        }
        
        $controller = ucfirst($controller).'Controller';
        $controller = new $controller(array('action'=>$action, 'params'=>$params));
        $controller->process();

        return TRUE;
    }

    /**   Capture errors */
    public static function Capture  ($exception) {
        header("HTTP/1.0 500");
        echo '<html><head><style>* {font-family:Verdana,"Microsoft YaHei"}</style></head><body style="padding:10px;"><h1 style="font-size:30px;">MVC Framework Error</h1><p style="padding:20px 0;">('.$exception->getCode().') '.$exception->getMessage().'</p></body></html>';
    } 

    /**
     * MVC Autoload static function (to load Model)
     *
     *  @desc UserInfoModel in MVC::$Path/model/user_info.model.php
     *  @usage spl_autoload_register('MVC::Autoload') 
     */
    public static function Autoload ($class) {

        // Autoload Controller
        if(preg_match('/Controller$/', $class)) {
            // convert UserInfoModel to user_info
            $file = MVC::StrUnderscore(str_replace('Controller','',$class));
            // convert user_info to /path/to/mvc/model/user_info.model.php
            $file = MVC::$Path.'controller'.DIRECTORY_SEPARATOR.$file.'.controller.php';

            if(!file_exists($file)) throw new MVCException('Controller file not found in '.str_replace(MVC::$Path, 'MVCPath', $file), self::$Code['CONTROLLER_NOT_FOUND']);
            require_once($file); 
            if(!class_exists($class)) throw new MVCException('Class '.$class.' not defined in file '.str_replace(MVC::$Path, 'MVCPath', $file), self::$Code['CONTROLLER_NOT_DEFINED']);
        }
        // Autoload Model
        elseif(preg_match('/Model$/', $class)) {
            // convert UserInfoModel to user_info
            $file = MVC::StrUnderscore(str_replace('Model','',$class));
            // convert user_info to /path/to/mvc/model/user_info.model.php
            $file = MVC::$Path.'model'.DIRECTORY_SEPARATOR.$file.'.model.php';

            if(!file_exists($file)) throw new MVCException('Model file not found in '.str_replace(MVC::$Path, 'MVCPath', $file), self::$Code['MODEL_NOT_FOUND']);
            require_once($file); 
            if(!class_exists($class)) throw new MVCException('Class '.$class.' not defined in file '.str_replace(MVC::$Path, 'MVCPath', $file), self::$Code['MODEL_NOT_DEFINED']);
        }
        

    }

    /**   String convert name to underscore (FooBar => foo_bar) */
    public static function StrUnderscore ($name) {
        if(empty($name)) return $name;
        return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $name));
    }
}

/**   MVCException */
class MVCException extends Exception {}

/**   Controller */
class Controller {
    
    /* User Data (assigned by mvc or controller , extracted and passed to views) */
    private $data ;

    /* System data */
    private $_data;

    /* Constructor */
	public function __construct ($options=array()) { 
        $this->_data = array(
            'controller' => strtolower(str_replace('Controller','',get_class($this))),
            'action' => isset($options['action']) ? $options['action'] : NULL,
            'params' => isset($options['params']) ? $options['params'] : array(),
            'layouts' => array('application'=>1,'controller'=>1),
            'view' => isset($options['action']) ? $options['action'] : NULL,
            'type' => 'view',
            'title' => NULL,
            'scripts' => array(),
            'styles' => array()
        );
        $this->data = array();

        /**
         * 自动加载util相关的类
         */
        function util_auto_load($class){
            $file = PATH.str_replace('_', DIRECTORY_SEPARATOR, $class).'.php';
            if(!file_exists($file)) return FALSE ;
            require_once($file);
            if(!class_exists($class)) return FALSE;
        }
        spl_autoload_register('util_auto_load');


        $curr_node  = $this->_data['controller'].'-'.$this->_data['action'];
        $write_list = array(
            'home-login'
        );
        if(in_array($curr_node,$write_list)){
            return ;
        }else{
            if(Cookie::get('uid')){
                return;
            }else{
                header("Location: /home/login");
            }
        }
	}
    
    /** Get Param */
    public function getParam ($key, $default=NULL) {
        return isset($this->_data['params'][$key]) ? $this->_data['params'][$key] : $default;
    }

    /* Get Action */
    public function getAction () {
        return $this->_data['action'];
    }

    /* Set Layouts ('application','controller') */
    public function addLayout ($layout) {
        if(!in_array($layout,array('application', 'controller'))) return;
        $this->_data['layouts'][$layout] = 1;
    }

    /* Rmv Layout ('application','controller') */
    public function rmvLayout ($layout) {
        if(!in_array($layout,array('application', 'controller'))) return;
        $this->_data['layouts'][$layout] = 0;
    }

    /* Set Type */
    public function setType ($type) {
        $this->_data['type'] = $type;
    }

    /* Set View */
    public function setView ($view) {
        $this->_data['view'] = $view;
    }

    /* Get View */
    public function getView ($view) {
        return $this->_data['view'];
    }

    /* Set Data (multi) */
    public function setData ($key, $val=NULL) {
        if(is_scalar($key)) { $this->data[$key] = $val; }
        else if(is_array($key)) { $this->data = array_merge($this->data, $key); }
    }

    /* Get Data */
    public function getData ($key, $default=NULL) {
        return isset($this->data[$key])?$this->data[$key] : $default;
    }

    /* Remove Data , but no system data (multi) */
    public function rmvData ($key=NULL) {
        if($key===NULL) { $this->data = array(); }
        else { unset($this->data[$key]); }
    }

    /* Set page title */
    function setTitle ($title) {
        $this->_data['title'] = $title;
    }

    /* Set meta description */
    function setDescription ($desc) {
        $this->_data['desc'] = str_replace('"', '', $desc);
    }

    /* Set meta keywords */
    function setKeywords ($keywords) {
        $this->_data['keywords'] = str_replace('"', '', $keywords);
    }

    /* Get title */
    function getTitle () {
        return $this->_data['title'];
    }

    /* Add Style */
    public function addStyle() {
        $args = func_get_args();
        $this->_data['styles'] = array_unique(array_merge($this->_data['styles'], $args));
    }

    /* Remove Style */
    public function rmvStyle() {
        $args = func_get_args();
        foreach($args as $style) {
            $key = array_search($style, $this->_data['styles']);
            if($key!==FALSE) unset($this->_data['styles'][$key]);
        }
    }

    /* Add Script */
    public function addScript() {
        $args = func_get_args();
        $this->_data['scripts'] = array_unique(array_merge($this->_data['scripts'], $args));
    }

    /* Remove Script */
    public function rmvScript() {
        $args = func_get_args();
        foreach($args as $script) {
            $key = array_search($script, $this->_data['scripts']);
            if($key!==FALSE) unset($this->_data['scripts'][$key]);
        }
    }

    /* Get && Check HTTP Method (first get from query '_method' for easy test) */
    public function getMethod () {
        return isset($_REQUEST['_method']) ? $_REQUEST['_method'] : $_SERVER['REQUEST_METHOD'];
    }

    public function isHead () {
        return $this->getMethod() == 'HEAD';
    }

    public function isGet () {
        return $this->getMethod() == 'GET';
    }

    public function isPost () {
        return $this->getMethod() == 'POST';
    }

    public function isPut () {
        return $this->getMethod() == 'PUT';
    }

    /* Get && Check HTTP Accept (first get from query '_accept' for easy test) */
    public function getAccept () {
        $accept = isset($_REQUEST['_accept']) ? $_REQUEST['_accept'] : $_SERVER['HTTP_ACCEPT'];
        $checks = array('html', 'json', 'text');
        foreach($checks as $check) {
            if(strpos($accept, $check)!==FALSE) {
                return $check;
            }
        }
        return NULL;
    }

    public function isJSON () {
        return $this->getAccept() == 'json';
    }

    /* Get queries, default value will be returned if the key provided is not set */
    public function request ($key, $default=NULL) {
        $result = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
        if(is_string($result) || is_int($result)) $result = trim($result);
        return $result;
    }
    
    /* Process */
	function process () {
		$result = $this->preAction(); 
        if($result!==FALSE) {
            $action = $this->_data['action'];
            if($action && method_exists($this, $action)) call_user_func_array(array($this,$action),$this->_data['params']);
        }
        $this->postAction();
        $this->preRender();
		switch($this->_data['type']) {
			case NULL : break;
			case 'view' :
                $views = array();
                if ($this->_data['layouts']['application']) { 
                    $path = MVC::$Path.'view'.DIRECTORY_SEPARATOR.'html.php';
                    if(file_exists($path)) $views[] = $path; 
                }
                if ($this->_data['layouts']['controller']) { 
                    $path = MVC::$Path.'view'.DIRECTORY_SEPARATOR.($this->_data['controller']).'.html.php';
                    if(file_exists($path)) $views[] = $path;  
                }
                $views[] = MVC::$Path.'view'.DIRECTORY_SEPARATOR.($this->_data['controller']).DIRECTORY_SEPARATOR.($this->_data['view']).'.html.php';
                foreach($views as $view) if(!file_exists($view)) throw new MVCException('View not found : $path'.DIRECTORY_SEPARATOR.str_replace(MVC::$Path, '', $view), MVC::$Code['VIEW_NOT_FOUND']);
                $data = $this->data;
                foreach($this->_data as $key => $val) { $data['_'.$key] = $val; }
                View::Display($views, $data);
				break;
			case 'text' :
				echo $this->data;
				break;
			case 'json' :
                header('Content-Type: application/json');
				echo json_encode($this->data);
				break;
			case 'xml' :
				break;
			case 'file' :
				break;
		}
		$this->postRender();
	}

    /* Reset to a new action (using server side reset to prevent client redirect) */
    function reset ($action, $params = NULL, $controller = NULL) {
        $this->_data['action'] = $action;
        $this->_data['view'] = $action;
    }

	/* Redirect to a new url */
	function redirect ($url) {
        // redirect by url
        if (is_string($url)) {
            header("Location: {$url}"); exit;
        }
        // redirect by inner
		else if(is_array($url)) {
			if(isset($url['action'])) {
                $action = $url['action'];
                $this->_data['action'] = $action;
                $this->_data['view'] = $action;
                call_user_func_array(array($this,$action),$this->_data['params']);
            }
		}
	}

    /* Response content and exit */
    function response ($data, $type = NULL) {
        if(!$type) $type = $this->getAccept();
        if($type == 'text' || $type == 'html') {
            echo $data ;
        }
        elseif($type == 'json') {
            header('Content-Type: application/json');
            echo json_encode($data);
        }
        die;
    }

	/* Before Action */
	function preAction () {
		return TRUE ;
	}
	
    /* After Action */
	function postAction () {
		return TRUE ;
	}

    /* Pre Render */
    function preRender () {
        return TRUE ;
    }

    /* Post Render */
    function postRender () {
        return TRUE ;
    }
}

/**   View */
class View {
    
    private static $_Templates ;
    private static $_Data;
    
    public static $VIEWS = array();
    public static $DATA = array();
    
    public static function Display ($views, $data) {
        self::$VIEWS = $views;
        self::$DATA = $data;
        self::Render();
    }

    /* render next views */
    public static function Render () {
        $__view = array_shift(self::$VIEWS);
        if($__view===NULL) return;
        extract(self::$DATA);
        require($__view);
    }
    
    /* include partial views */
    public static function RenderPartial ($template='', $data=array()) {
        extract($data); require(MVC::$Path.'view'.DIRECTORY_SEPARATOR.$template.'.html.php');
    }
}

function view_render () {
    View::Render();
}
function view_render_partial ($template='', $data=array()) {
    View::RenderPartial($template, $data);
}

/** Model
 *  
 *      
 *      
 *
 *  Static Methods 
 *
 *
 *  // create new object (factory method)
 *  $user = Model::Load('User');
 *  
 *  // Select Methods
 *  Model::SelectUsers($where, $option); 
 *  //=> UserModel::SelectList($conditions, $options);
 *  Model::SelectUser($id); //=> UserModel::Select($id); 
 *  
 *  Model::CountUser($where); //=> UserModel::Count($where);
 *  
 *  Model::InsertUser($data);
 *  Model::InsertUserList($data_list);
 *
 *  Model::UpdateUser();
 *  Model::UpdateUserList();
 *
 *  Model::DeleteUser();
 *  
 *  Model::QueryUser($sql); //=> UserModel::Query($sql);
 *  
 *  // 
 *  $user = Model::Select('db.table', );
 *  
 *  // select user 
 *  UserModel::Select($);
 *  //
 *  
 *  
 *  
 *  
 *  
 */
class Model {
    /** 
     */
    public static function Config () {
    }

    /**   Validate data for insert and update
     *     
     *     @param array $data 
     *     @param bool $all 
     *         TRUE : validate needed fields all (mostly for insert operations)
     *         FALSE : validate existed fields only (mostly for update operations)
     *     @return
     *         TRUE : validate success
     *         array() : array ( field => errormsg , ... )
     */
    protected static function Validate ($data, $all = TRUE) {
        return TRUE;
    }

    /**   Magic static methods (for db operations)
     *     
     *     @usage
     *     
     *     @todo
     *         check sub classes method
     */
    public static function __callStatic ($name, $arguments) {
        // Call crud methods
        if(preg_match('/^(Select|Update|Insert|Delete|Count|Increase)([a-zA-Z0-9]+)$/', $name, $matches)){
            // db_crud 
            $crud = $matches[1];
            $db_crud = 'db_'.strtolower($crud);
            // convert 'ClassName' to 'class_name'
            $table = MVC::StrUnderscore($matches[2]);
            // some action
            if ($crud == 'Select') {
                // todo
            }
            // add db & table arguments
            $args = array_merge(array(MVC::$DB, $table), $arguments);
            return call_user_func_array($db_crud, $args);
        }
        else {
            return FALSE;
        }
    }

    /* instance methods */
    protected $_data = NULL;
    function __construct ($data = NULL) {
        $this->_data = $data;
    }

    public function __isset ( $name ) {
        return isset($this->_data[$name]);
    }

	/**   Set Property
     *      Set property named XXX , call set_XXX() methods if exists , then set data 
     *  Example
     *      $model->name = 'a' ; // $model->set_name('a'); => $model->data['name'] = 'a';
	 */
	public function __set ( $name , $value ) {
		if ( method_exists( $this , 'set_'.$name ) ) {
			$this->{'set_'.$name}($value);	
		}
		else {
			$this->_data[$name] = $value ;
		}
	}
    
    /** Get Property
     *      Get property named XXX , call get_XXX() methods if exists , then get data 
     *  Example
     *      echo $model->name ; // $model->get_name(); => return $model->data['name'];
     *  Tips
     *      you can define function like this
     *      public function get_name () {
     *         // save in property for cache data , because next time will auto get from property
     *         $this->name = get_name_from_db(); 
     *         return $this->name;
     *      }
	 */
	public function __get ( $name ) {
        if ( isset($this->_data[$name]) ) {
            return $this->_data[$name];
        }
		elseif ( method_exists( $this , 'get_'.$name ) ) {
			return $this->{'get_'.$name}();
		}
		else {
			return NULL;
		}
	}

    /** PHP BUG : sub class cant override __callStatic , but using __call
     */
    public function __call ( $name, $arguments ) { 
        if ( method_exists( $this , 'get_'.$name ) ) {
            return call_user_func_array(array($this, 'get_'.$name), $arguments);
        }
        else { 
            // PHP BUG
            return self::__callStatic($name, $arguments);
        }
    }

}