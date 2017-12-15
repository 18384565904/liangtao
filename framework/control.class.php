<?php
/**
 * The control class file of ZenTaoPHP framework.
 *
 * The author disclaims copyright to this source code.  In place of
 * a legal notice, here is a blessing:
 * 
 *  May you do good and not evil.
 *  May you find forgiveness for yourself and forgive others.
 *  May you share freely, never taking more than you give.
 */
/**
 * The base class of control.
 * 
 * @package framework
 */
class control
{
    /**
     * The global $app object.
     * 
     * @var object
     * @access protected
     */
    protected $app;

    /**
     * The global $config object.
     * 
     * @var object
     * @access protected
     */
    protected $config;

    /**
     * The global $lang object.
     * 
     * @var object
     * @access protected
     */
    protected $lang;

    /**
     * The global $dbh object, the database connection handler.
     * 
     * @var object
     * @access protected
     */
    protected $dbh;

    /**
     * The $dao object, used to access or update database.
     * 
     * @var object
     * @access protected
     */
    public $dao,$dao2;

    /**
     * The $post object, used to access the $_POST var.
     * 
     * @var ojbect
     * @access public
     */
    public $post;

    /**
     * The $get object, used to access the $_GET var.
     * 
     * @var ojbect
     * @access public
     */
    public $get;

    /**
     * The $session object, used to access the $_SESSION var.
     * 
     * @var ojbect
     * @access public
     */
    public $session;

    /**
     * The $server object, used to access the $_SERVER var.
     * 
     * @var ojbect
     * @access public
     */
    public $server;

    /**
     * The $cookie object, used to access the $_COOKIE var.
     * 
     * @var ojbect
     * @access public
     */
    public $cookie;

    /**
     * The $global object, used to access the $_GLOBAL var.
     * 
     * @var ojbect
     * @access public
     */
    public $global;

    /**
     * The name of current module.
     * 
     * @var string
     * @access protected
     */
    protected $moduleName;

    /**
     * The vars assigned to the view page.
     * 
     * @var object
     * @access public
     */
    public $view; 

    /**
     * The type of the view, such html, json.
     * 
     * @var string
     * @access private
     */
    private $viewType;

    /**
     * The content to display.
     * 
     * @var string
     * @access private
     */
    private $output;

    /**
     * The directory seperator.
     * 
     * @var string
     * @access protected
     */
    protected $pathFix;

    /**
     * The construct function.
     *
     * 1. global the global vars, refer them by the class member such as $this->app.
     * 2. set the pathes of current module, and load it's mode class.
     * 3. auto assign the $lang and $config to the view.
     * 
     * @access public
     * @return void
     */
    public function __construct($moduleName = '', $methodName = '')
    {
        /* Global the globals, and refer them to the class member. */
        global $app, $config, $lang, $dbh;
        $this->app        = $app;
        $this->config     = $config;
        $this->lang       = $lang;
        $this->dbh        = $dbh;
        $this->pathFix    = $this->app->getPathFix();
        $this->viewType   = $this->app->getViewType();

        $this->view = new stdclass();
        $this->view->header = new stdclass();
		$this->sqlin();
        $this->setModuleName($moduleName);
        $this->setMethodName($methodName);

        /* Load the model file auto. */
        $this->loadModel();
        /* Assign them to the view. */
        $this->assign('app',    $app);
        $this->assign('lang',   $lang);
        $this->assign('config', $config);
		$this->sqlin();
        $this->setSuperVars();
    }

    //-------------------- Model related methods --------------------//

    /* Set the module name. 
     * 
     * @param   string  $moduleName     The module name, if empty, get it from $app.
     * @access  private
     * @return  void
     */
    private function setModuleName($moduleName = '')
    {
        $this->moduleName = $moduleName ? $moduleName : $this->app->getModuleName();
    }

    /* Set the method name. 
     * 
     * @param   string  $methodName    The method name, if empty, get it from $app.
     * @access  private
     * @return  void
     */
    private function setMethodName($methodName = '')
    {
        $this->methodName = $methodName ? $methodName : $this->app->getMethodName();
    }
	
	//规则
	public function setRules(){
		$quiescence = array('*');
		//存在规则函数
		if(method_exists($this,'accessRules')){
			$rules = $this->accessRules();
			
			foreach($rules as $key=>$rule){
				if(is_array($rule) && isset($rule[0]))
				{
					$validate = '';
					if(isset($rule['cookes'])){
						$validate = isset($_COOKIE[$rule['cookes']])?$_COOKIE[$rule['cookes']]:'';
					}
					$actions = '';
					$roles = '';
					
					if(isset($rule['actions']))
						$actions = is_array($rule['actions'])?array_flip($rule['actions']):array_flip($quiescence);
					else
						$actions = array_flip($quiescence);
						
					$users = isset($rule['users'])?$rule['users']:$quiescence[0];
					
					if(isset($rule['roles']))
						$roles = is_array($rule['roles'])?array_flip($rule['roles']):'';
					else
						$roles = '';
						
					if($rule[0] === 'deny'){
						$denyValidate = $this->denyValidate($actions);
						if(!$denyValidate){
							//所有
							if($users === '*'){
								if($roles){//存在指定的类型
									if($validate == ''){ return false; break; }
									if(array_key_exists($validate,$roles)){ return false; break; }
								} else {
									return false; break;
								}
							} else if($users === '?'){
								if($validate == '')
									return false;
									break;	
							} else if($users === '@'){
								if($validate == ''){ return false; break; }
								if($roles){//存在指定的类型
									if(array_key_exists($validate,$roles)){ return false; break; }
								} else {
									return false; break;
								}
							}
						}		
					} else if($rule[0] === 'allow'){
						$allowValidate = $this->allowValidate($actions);
						if($allowValidate){
							if($users === '*'){
								if($roles){
									if(array_key_exists($validate,$roles)){ return true; break; }
								} else {
									return true; break;
								}
							} else if($users === '?'){
								if($validate == '')
									return true;
									break;
							} else if($users === '@'){
								if($validate != '')
									if($roles){
										if(array_key_exists($validate,$roles)){ return true; break; }
									} else {
										return true; break;
									}
									
							}
						}
					}
				}
			}
			return true;
		}
	}
	
	public function denyValidate($actions){
		global $app;
		if(array_key_exists('*',$actions)){
			return false;
		} else if(array_key_exists($app->getMethodName(),$actions)){
			return false;
		} else {
			return true;
		}
	} 
	
	public function allowValidate($actions){
		global $app;
		if(array_key_exists('*',$actions)){
			return true;
		} else if(array_key_exists($app->getMethodName(),$actions)){
			return true;
		} else {
			return false;
		}
	} 

    /**
     * Load the model file of one module.
     * 
     * @param   string      $methodName    The method name, if empty, use current module's name.
     * @access  public
     * @return  object|bool If no model file, return false. Else return the model object.
     */
    public function loadModel($moduleName = '')
    {
        if(empty($moduleName)) $moduleName = $this->moduleName;
        $modelFile = helper::setModelFile($moduleName);

        /* If no model file, try load config. */
        if(!helper::import($modelFile)) 
        {
            $this->app->loadConfig($moduleName, false);
            $this->app->loadLang($moduleName);
           // $this->dao = new dao();
            $this->dao = new dao();
            $this->dao2 = new dao2();
            return false;
        }

        $modelClass = class_exists('ext' . $moduleName. 'model') ? 'ext' . $moduleName . 'model' : $moduleName . 'model';
        if(!class_exists($modelClass)) $this->app->error(" The model $modelClass not found", __FILE__, __LINE__, $exit = true);

        $this->$moduleName = new $modelClass();
        $this->dao = $this->$moduleName->dao;
        $this->dao2 = $this->$moduleName->dao2;
        return $this->$moduleName;
    }

    /**
     * Set the super vars.
     * 
     * @access protected
     * @return void
     */
    protected function setSuperVars()
    {
        $this->post    = $this->app->post;
        $this->get     = $this->app->get;
        $this->server  = $this->app->server;
        $this->session = $this->app->session;
        $this->cookie  = $this->app->cookie;
        $this->global  = $this->app->global;
    }

    //-------------------- View related methods --------------------//
    
    /**
     * Set the view file, thus can use fetch other module's page.
     * 
     * @param  string   $moduleName    module name
     * @param  string   $methodName    method name
     * @access private
     * @return string  the view file
     */
    private function setViewFile($moduleName, $methodName)
    {
        $moduleName = trim($moduleName);
        $methodName = trim($methodName);

        $modulePath  = $this->app->getModulePath($moduleName);
        $viewExtPath = $this->app->getModuleExtPath($moduleName, 'view');

        /* The main view file, extension view file and hook file. */
        $mainViewFile = $modulePath . 'view' . $this->pathFix . $methodName . '.' . $this->viewType . '.php';
        $extViewFile  = $viewExtPath . $methodName . ".{$this->viewType}.php";
        $extHookFiles = helper::ls($viewExtPath, '.hook.php');

        $viewFile = file_exists($extViewFile) ? $extViewFile : $mainViewFile;
        if(!is_file($viewFile)) $this->app->error("the view file $viewFile not found", __FILE__, __LINE__, $exit = true);
        if(!empty($extHookFiles)) return array('viewFile' => $viewFile, 'hookFiles' => $extHookFiles);
        return $viewFile;
    }

    /**
     * Get the extension file of an view.
     * 
     * @param  string $viewFile 
     * @access public
     * @return string|bool  If extension view file exists, return the path. Else return fasle.
     */
    public function getExtViewFile($viewFile)
    {
        $extPath     = dirname(dirname(realpath($viewFile))) . '/ext/view/';
        $extViewFile = $extPath . basename($viewFile);
        if(file_exists($extViewFile))
        {
            helper::cd($extPath);
            return $extViewFile;
        }
        return false;
    }

    /**
     * Get css code for a method. 
     * 
     * @param  string    $moduleName 
     * @param  string    $methodName 
     * @access private
     * @return string
     */
    private function getCSS($moduleName, $methodName)
    {
        $moduleName = trim($moduleName);
        $methodName = trim($methodName);
        $modulePath = $this->app->getModulePath($moduleName);

        $css = '';
        $mainCssFile   = $modulePath . 'css' . $this->pathFix . 'common.css';
        $methodCssFile = $modulePath . 'css' . $this->pathFix . $methodName . '.css';
        if(file_exists($mainCssFile))   $css .= file_get_contents($mainCssFile);
        if(is_file($methodCssFile))     $css .= file_get_contents($methodCssFile);
        return $css;
    }

    /**
     * Get js code for a method. 
     * 
     * @param  string    $moduleName 
     * @param  string    $methodName 
     * @access private
     * @return string
     */
    private function getJS($moduleName, $methodName)
    {
        $moduleName = trim($moduleName);
        $methodName = trim($methodName);
        $modulePath = $this->app->getModulePath($moduleName);

        $js = '';
        $mainJsFile   = $modulePath . 'js' . $this->pathFix . 'common.js';
        $methodJsFile = $modulePath . 'js' . $this->pathFix . $methodName . '.js';
        if(file_exists($mainJsFile))   $js .= file_get_contents($mainJsFile);
        if(is_file($methodJsFile))     $js .= file_get_contents($methodJsFile);
        return $js;
    }

    /**
     * Assign one var to the view vars.
     * 
     * @param   string  $name       the name.
     * @param   mixed   $value      the value.
     * @access  public
     * @return  void
     */
    public function assign($name, $value)
    {
        $this->view->$name = $value;
    }

    /**
     * Clear the output.
     * 
     * @access public
     * @return void
     */
    public function clear()
    {
        $this->output = '';
    }

    /**
     * Parse view file. 
     *
     * @param  string $moduleName    module name, if empty, use current module.
     * @param  string $methodName    method name, if empty, use current method.
     * @access public
     * @return string the parsed result.
     */
    public function parse($moduleName = '', $methodName = '')
    {
        if(empty($moduleName)) $moduleName = $this->moduleName;
        if(empty($methodName)) $methodName = $this->methodName;

        if($this->viewType == 'json')
        {
            $this->parseJSON($moduleName, $methodName);
        }
        else
        {
            $this->parseDefault($moduleName, $methodName);
        }
        return $this->output;
    }

    /**
     * Parse json format.
     *
     * @param string $moduleName    module name
     * @param string $methodName    method name
     * @access private
     * @return void
     */
    private function parseJSON($moduleName, $methodName)
    {
        unset($this->view->app);
        unset($this->view->config);
        unset($this->view->lang);
        unset($this->view->pager);
        unset($this->view->header);
        unset($this->view->position);
        unset($this->view->moduleTree);

        $output['status'] = is_object($this->view) ? 'success' : 'fail';
        $output['data']   = json_encode($this->view);
        $output['md5']    = md5(json_encode($this->view));
        $this->output     = json_encode($output);
    }

    /**
     * Parse default html format.
     *
     * @param string $moduleName    module name
     * @param string $methodName    method name
     * @access private
     * @return void
     */
    private function parseDefault($moduleName, $methodName)
    {
        /* Set the view file. */
        $viewFile = $this->setViewFile($moduleName, $methodName);
        if(is_array($viewFile)) extract($viewFile);

        /* Get css and js. */
        $css = $this->getCSS($moduleName, $methodName);
        $js  = $this->getJS($moduleName, $methodName);
        if($css) $this->view->pageCss = $css;
        if($js)  $this->view->pageJS  = $js;

        /* Change the dir to the view file to keep the relative pathes work. */
        $currentPWD = getcwd();
        chdir(dirname($viewFile));

        extract((array)$this->view);
        ob_start();
        include $viewFile;
        if(isset($hookFiles)) foreach($hookFiles as $hookFile) include $hookFile;
        $this->output .= ob_get_contents();
        ob_end_clean();

        /* At the end, chang the dir to the previous. */
        chdir($currentPWD);
    }

    /**
     * Get the output of one module's one method as a string, thus in one module's method, can fetch other module's content.
     * 
     * If the module name is empty, then use the current module and method. If set, use the user defined module and method.
     *
     * @param   string  $moduleName    module name.
     * @param   string  $methodName    method name.
     * @param   array   $params        params.
     * @access  public
     * @return  string  the parsed html.
     */
    public function fetch($moduleName = '', $methodName = '', $params = array())
    {
        if($moduleName == '') $moduleName = $this->moduleName;
        if($methodName == '') $methodName = $this->methodName;
        if($moduleName == $this->moduleName and $methodName == $this->methodName) 
        {
            $this->parse($moduleName, $methodName);
            return $this->output;
        }

        /* Set the pathes and files to included. */
        $modulePath        = $this->app->getModulePath($moduleName);
        $moduleControlFile = $modulePath . 'control.php';
        $actionExtFile     = $this->app->getModuleExtPath($moduleName, 'control') . $methodName . '.php';
        $file2Included     = file_exists($actionExtFile) ? $actionExtFile : $moduleControlFile;

        /* Load the control file. */
        if(!is_file($file2Included)) $this->app->error("The control file $file2Included not found", __FILE__, __LINE__, $exit = true);
        $currentPWD = getcwd();
        chdir(dirname($file2Included));
        if($moduleName != $this->moduleName) helper::import($file2Included);
        
        /* Set the name of the class to be called. */
        $className = class_exists("my$moduleName") ? "my$moduleName" : $moduleName;
        if(!class_exists($className)) $this->app->error(" The class $className not found", __FILE__, __LINE__, $exit = true);

        /* Parse the params, create the $module control object. */
        if(!is_array($params)) parse_str($params, $params);
        $module = new $className($moduleName, $methodName);

        /* Call the method and use ob function to get the output. */
        ob_start();
        call_user_func_array(array($module, $methodName), $params);
        $output = ob_get_contents();
        ob_end_clean();

        /* Return the content. */
        unset($module);
        chdir($currentPWD);
        return $output;
    }

    /**
     * Print the content of the view. 
     * 
     * @param   string  $moduleName    module name
     * @param   string  $methodName    method name
     * @access  public
     * @return  void
     */
    public function display($moduleName = '', $methodName = '')
    {
        if(empty($this->output)) $this->parse($moduleName, $methodName);
        echo $this->output;
    }

    /**
     * Create a link to one method of one module.
     * 
     * @param   string         $moduleName    module name
     * @param   string         $methodName    method name
     * @param   string|array   $vars          the params passed, can be array(key=>value) or key1=value1&key2=value2
     * @param   string         $viewType      the view type
     * @access  public
     * @return  string the link string.
     */
    public function createLink($moduleName, $methodName = 'index', $vars = array(), $viewType = '')
    {
        if(empty($moduleName)) $moduleName = $this->moduleName;
        return helper::createLink($moduleName, $methodName, $vars, $viewType);
    }

    /**
     * Create a link to the inner method of current module.
     * 
     * @param   string         $methodName    method name
     * @param   string|array   $vars          the params passed, can be array(key=>value) or key1=value1&key2=value2
     * @param   string         $viewType      the view type
     * @access  public
     * @return  string  the link string.
     */
    public function inlink($methodName = 'index', $vars = array(), $viewType = '')
    {
        return helper::createLink($this->moduleName, $methodName, $vars, $viewType);
    }

    /**
     * Location to another page.
     * 
     * @param   string   $url   the target url.
     * @access  public
     * @return  void
     */
    public function locate($url)
    {
        header("location: $url");
		//echo '<script type="text/javascript">top.location.href="'.$url.'";</script>';
        exit;
    }

    public function topLocate($url)
    {
        //header("location: $url");
		echo '<script type="text/javascript">top.location.href="'.$url.'";</script>';
        exit;
    }

	public function dowith_sql($str)
	{
	   $str = str_replace("and","",$str);
	   $str = str_replace("execute","",$str);
		//$str = str_replace("update","",$str);	//自定义函数名带有 'update'
		//$str = str_replace("count","",$str);
	   $str = str_replace("chr","",$str);
	   $str = str_replace("mid","",$str);
	   $str = str_replace("master","",$str);
	   $str = str_replace("truncate","",$str);
	   $str = str_replace("char","",$str);
	   $str = str_replace("declare","",$str);
	   //$str = str_replace("select","",$str);
	   //$str = str_replace("create","",$str);
	   //$str = str_replace("delete","",$str);
	   //$str = str_replace("insert","",$str);
	   //$str = str_replace("'","",$str);
	   //$str = str_replace('"',"",$str);
	   $str = str_replace(" ","",$str);
	   $str = str_replace("or","",$str);
	   $str = str_replace("=","",$str);
	   $str = str_replace(" ","",$str);
	   $str = str_replace("(","",$str);
	   $str = str_replace(")","",$str);
	   $str = str_replace("<","",$str);
	   $str = str_replace(">","",$str);
	   //$str = str_replace("-","",$str);	//课件文件名有 '-'
	   $str = str_replace("*","",$str);
	   //echo $str;
	   return $str;
	}
	//aticle()防SQL注入函数
	public function sqlin()
	{
	   foreach ($_GET as $key=>$value)
	   {
		   $_GET[$key]=$this->dowith_sql($value);
	   }
	   foreach ($_POST as $key=>$value)
	   {
		   $_POST[$key]=$this->dowith_sql($value);
	   }
	}

}
