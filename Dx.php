<?php
/**
* @package Dx Framework
* @author Davidix
* @copyright Copyright (c) 2010 - 2020 Davidix
*/

defined('_JEXEC') or die ('resticted aceess');

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filter.filteroutput');


JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

class Dx
{
	
	private static $_instance;
	private $document;
	private $importedFiles = array();
	private $_less;

	private $load_pos;

	/* Database Fields */
	public $db;
	public $tbl;
	public $dbname;
	public $action;

	//initialize
	public function __construct()
	{
	}

	//making self object for singleton method
	final public static function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
			self::getInstance()->getDocument();
		}

		return self::$_instance;
	}

	/**
	* Get Document
	*
	* @param string $key
	*/
	public static function getDocument($key = false)
	{
		self::getInstance()->document = JFactory::getDocument();
		$doc                          = self::getInstance()->document;
		if (is_string($key))
		{
			return $doc->$key;
		}

		return $doc;
	}

	public static function getParam($key)
	{
		$params = JFactory::getApplication()->getTemplate(true)->params;

		return $params->get($key);
	}
	//Body Class
	public static function bodyClass($class = '')
	{
		$app       = JFactory::getApplication();
		$doc       = JFactory::getDocument();
		$language  = $doc->language;
		$direction = $doc->direction;
		$option    = str_replace('_', '-', $app->input->getCmd('option', ''));
		$view      = $app->input->getCmd('view', '');
		$layout    = $app->input->getCmd('layout', '');
		$task      = $app->input->getCmd('task', '');
		$itemid    = $app->input->getCmd('Itemid', '');
		$sitename  = $app->get('sitename');

		if ($view == 'modules')
		{
			$layout = 'edit';
		}

		return 'site ' . $option
		. ' view-' . $view
		. ($layout ? ' layout-' . $layout : ' no-layout')
		. ($task ? ' task-' . $task : ' no-task')
		. ($itemid ? ' itemid-' . $itemid : '')
		. ($language ? ' ' . $language : '')
		. ($direction ? ' ' . $direction : '')
		. ($class ? ' ' . $class : '');
	}


	
	//Get view
	public static function view($class = '')
	{
		$app    = JFactory::getApplication();
		$view   = $app->input->getCmd('view', '');
		$layout = $app->input->getCmd('layout', '');

		if (($view == 'modules'))
		{
			$layout = 'edit';
		}

		return $layout;
	}

	//Get Template name
	public static function getTemplate()
	{
		return JFactory::getApplication()->getTemplate();
	}

	//Get Template URI
	public static function getTemplateUri()
	{
		return JURI::base(true) . '/templates/' . self::getTemplate();
	}

	/**
	* Get or set Current Template or any Component param. If value not setted params get and return,
	* else set params
	*
	* @param string $name
	* @param mixed  $value
	*/
	public static function Param($cmp_name=false, $name = true, $value = null)
	{
		/** get set params for current template **/
		if(!$cmp_name)
		{	
			// if $name = true, this will return all param data
			if (is_bool($name) and $name == true)
			{
				return JFactory::getApplication()->getTemplate(true)->params;
			}
			// if $value = null, this will return specific param data
			if (is_null($value))
			{
				return JFactory::getApplication()->getTemplate(true)->params->get($name);
			}
			// if $value not = null, this will set a value in specific name.
			if($value)
			{
				$prms = JFactory::getApplication()->getTemplate(true)->params;
				$prms->set($name, $value);
				$styleId = self::getActiveStyle();
				JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_templates/tables');
				$table = JTable::getInstance('Style', 'TemplatesTable', array());
				$table->load($styleId);
				$table->bind(array('params' => $prms->toString()));

				// check for error
				if (!$table->check()) {
					echo $table->getError();
					return false;
				}
				// Save to database
				if (!$table->store()) {
					echo $table->getError();
					return false;
				}
			}
			$data = JFactory::getApplication()->getTemplate(true)->params->get($name);

		}
		else /* get set params for any component */
		{
			// Load the current component params.
			$params = JComponentHelper::getParams($cmp_name);
			if($value)
			{
				// Set new value of param(s)
				$params->set($name, $value);

				// Save the parameters
				$componentid = JComponentHelper::getComponent($cmp_name)->id;
				$table = JTable::getInstance('extension');
				$table->load($componentid);
				$table->bind(array('params' => $params->toString()));

				// check for error
				if (!$table->check()) {
					echo $table->getError();
					return false;
				}
				// Save to database
				if (!$table->store()) {
					echo $table->getError();
					return false;
				}
			}
			else
			{
				return is_string($name)? $params->get($name) : $params;
			}
		}
	}

	/**
	* Importing features
	*
	* @access private
	*/
	private $inPositions = array();
	public $loadFeature = array();

	private static function importFeatures()
	{

		$template = JFactory::getApplication()->getTemplate();
		$path     = JPATH_THEMES . '/' . $template . '/features';

		if (file_exists($path))
		{
			$files = JFolder::files($path, '.php');

			if (count($files))
			{

				foreach ($files as $key => $file)
				{

					include_once $path . '/' . $file;
					$name = JFile::stripExt($file);

					$class = 'Helix3Feature' . ucfirst($name);
					$class = new $class(self::getInstance());

					$position = $class->position;
					$load_pos = (isset($class->load_pos) && $class->load_pos) ? $class->load_pos : '';

					self::getInstance()->inPositions[] = $position;

					if (!empty($position))
					{

						self::getInstance()->loadFeature[$position][$key]['feature'] = $class->renderFeature();
						self::getInstance()->loadFeature[$position][$key]['load_pos'] = $load_pos;
					}
				}
			}
		}

		return self::getInstance();
	}


	//Count Modules
	public static function countModules($position)
	{
		$doc = JFactory::getDocument();

		return ($doc->countModules($position) or self::hasFeature($position));
	}

	public static function loadModulePos($module)
    {
		echo JHtml::_('content.prepare','{loadposition   '.$module.'}');
    }

	/**
	* Has feature
	*
	* @param string $position
	*/

	public static function hasFeature($position)
	{

		if (in_array($position, self::getInstance()->inPositions))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Add Less
	*
	* @param mixed $less
	* @param mixed $css
	*
	* @return self
	*/
	public static function addLess($less, $css, $attribs = array())
	{
		$template  = JFactory::getApplication()->getTemplate();
		$themepath = JPATH_THEMES . '/' . $template;

		if (self::getParam('lessoption') and self::getParam('lessoption') == '1')
		{
			if (file_exists($themepath . "/less/" . $less . ".less"))
			{
				self::getInstance()->autoCompileLess($themepath . "/less/" . $less . ".less", $themepath . "/css/" . $css . ".css");
			}
		}
		self::getInstance()->addCSS($css . '.css', $attribs);

		return self::getInstance();
	}

	private static function addLessFiles($less, $css)
	{

		$less = self::getInstance()->file('less/' . $less . '.less');
		$css  = self::getInstance()->file('css/' . $css . '.css');
		self::getInstance()->less()->compileFile($less, $css);

		echo $less;
		die;

		return self::getInstance();
	}

	/**
	* Add stylesheet
	*
	* @param mixed $sources . string or array
	*
	* @return self
	*/
	public static function addCSS($sources, $attribs = array())
	{

		$template = JFactory::getApplication()->getTemplate();
		$path     = JPATH_THEMES . '/' . $template . '/css/';

		$srcs = array();

		if (is_string($sources))
		{
			$sources = explode(',', $sources);
		}
		if (!is_array($sources))
		{
			$sources = array($sources);
		}

		foreach ((array) $sources as $source)
		$srcs[] = trim($source);

		foreach ($srcs as $src)
		{

			if (file_exists($path . $src))
			{
				self::getInstance()->document->addStyleSheet(JURI::base(true) . '/templates/' . $template . '/css/' . $src, 'text/css', null, $attribs);
			}
			else
			{
				if ($src != 'custom.css')
				{
					self::getInstance()->document->addStyleSheet($src, 'text/css', null, $attribs);
				}
			}
		}

		return self::getInstance();
	}


	/**
	* Add javascript
	* @param mixed  $sources   . string or array
	* @param string $seperator . default is , (comma)
	*
	* @return self
	*/

	public static function addJS($sources, $seperator = ',')
	{

		$srcs = array();

		$template = JFactory::getApplication()->getTemplate();
		$path     = JPATH_THEMES . '/' . $template . '/js/';

		if (is_string($sources))
		{
			$sources = explode($seperator, $sources);
		}
		if (!is_array($sources))
		{
			$sources = array($sources);
		}

		foreach ((array) $sources as $source)
		$srcs[] = trim($source);

		foreach ($srcs as $src)
		{

			if (file_exists($path . $src))
			{
				self::getInstance()->document->addScript(JURI::base(true) . '/templates/' . $template . '/js/' . $src);
			}
			else
			{
				if ($src != 'custom.js')
				{
					self::getInstance()->document->addScript($src);
				}
			}
		}

		return self::getInstance();
	}

	/**
	* Add Inline Javascript
	*
	* @param mixed $code
	*
	* @return self
	*/
	public function addInlineJS($code)
	{
		self::getInstance()->document->addScriptDeclaration($code);

		return self::getInstance();
	}

	/**
	* Add Inline CSS
	*
	* @param mixed $code
	*
	* @return self
	*/
	public function addInlineCSS($code)
	{
		self::getInstance()->document->addStyleDeclaration($code);

		return self::getInstance();
	}

	/**
	* Less Init
	*
	*/
	public static function lessInit()
	{

		require_once __DIR__ . '/libs/lessc.inc.php';

		self::getInstance()->_less = new helix3_lessc();

		return self::getInstance();
	}

	/**
	* Instance of Less
	*/
	public static function less()
	{
		return self::getInstance()->_less;
	}

	/**
	* Set Less Variables using array key and value
	*
	* @param mixed $array
	*
	* @return self
	*/
	public static function setLessVariables($array)
	{
		self::getInstance()->less()->setVariables($array);

		return self::getInstance();
	}

	/**
	* Set less variable using name and value
	*
	* @param mixed $name
	* @param mixed $value
	*
	* @return self
	*/
	public static function setLessVariable($name, $value)
	{
		self::getInstance()->less()->setVariables(array($name => $value));

		return self::getInstance();
	}

	/**
	* Compile less to css when less modified or css not exist
	*
	* @param mixed $less
	* @param mixed $css
	*
	* @return self
	*/
	private static function autoCompileLess($less, $css)
	{
		// load the cache
		$template  = JFactory::getApplication()->getTemplate();
		$cachePath = JPATH_CACHE . '/com_templates/templates/' . $template;
		$cacheFile = $cachePath . '/' . basename($css . ".cache");

		if (file_exists($cacheFile))
		{
			$cache = unserialize(JFile::read($cacheFile));

			//If root changed then do not compile
			if (isset($cache['root']) && $cache['root'])
			{
				if ($cache['root'] != $less)
				{
					return self::getInstance();
				}
			}
		}
		else
		{
			$cache = $less;
		}

		$lessInit = self::getInstance()->less();
		$newCache = $lessInit->cachedCompile($cache);

		if (!is_array($cache) || $newCache["updated"] > $cache["updated"])
		{

			if (!file_exists($cachePath))
			{
				JFolder::create($cachePath, 0755);
			}

			file_put_contents($cacheFile, serialize($newCache));
			file_put_contents($css, $newCache['compiled']);
		}

		return self::getInstance();
	}

	private static function resetCookie($name)
	{
		if (JRequest::getVar('reset', '', 'get') == 1)
		{
			setcookie($name, '', time() - 3600, '/');
		}
	}

	/**
	* Preset
	*
	*/
	public static function Preset()
	{
		$template = JFactory::getApplication()->getTemplate();
		$name     = $template . '_preset';

		if (isset($_COOKIE[$name]))
		{
			$current = $_COOKIE[$name];
		}
		else
		{
			$current = self::getParam('preset');
		}

		return $current;
	}

	public static function PresetParam($name)
	{
		return self::getParam(self::getInstance()->Preset() . $name);
	}

	/**
	* Convert object to array
	*/
	public static function object_to_array($obj) {
		if(is_object($obj)) $obj = (array) $obj;
		if(is_array($obj)) {
			$new = array();
			foreach($obj as $key => $val) {
				$new[$key] = self::object_to_array($val);
			}
		}
		else $new = $obj;
		return $new;
	}


	
	/* Handy function for Debug */ 
	public static function printObj($obj=null) {
		if(isset($obj))
		{
			echo '<pre>';
			print_r($obj);
			echo '</pre><hr/>';
		}
	}

	public static function throwError($message,$code=null){
		if(!empty($code))
			throw new Exception($message);
		else
			throw new Exception($message);
	}


	public static function getRandomString($length = 10){
		
		$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
		$randomString = '';
	
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, strlen($characters) - 1)];
		}
	
		return $randomString;
	}
	
	public static function validateFilepath($filepath,$errorPrefix=null){
		if(file_exists($filepath) == true)
			return(false);
		if($errorPrefix == null)
			$errorPrefix = "File";
		$message = $errorPrefix." $filepath not exists!";
		self::throwError($message);
	}
	

	public static function validateDir($pathDir, $errorPrefix=null){
		if(is_dir($pathDir) == true)
			return(false);
		
		if($errorPrefix == null)
			$errorPrefix = "Directory";
		$message = $errorPrefix." $pathDir not exists!";
		self::throwError($message);
	}

	public static function limitStringSize($str, $numChars = 10, $addDots = true){
		
		$encoding = "UTF-8";
		
		if(function_exists("mb_strlen") == false)
			return($str);
			
		if(mb_strlen($str, $encoding) <= $numChars)
			return($str);
		
		if($addDots)
			$str = mb_substr($str, 0, $numChars-3, $encoding)."...";				
		else
			$str = mb_substr($str, 0, $numChars, $encoding);
		
		
		return($str);
	}

	public static function font_key_search($font, $fonts) {

		foreach ($fonts as $key => $value) {
			if($value['family'] == $font) {
				return $key;
			}
		}

		return 0;
	}

	public static function getRandomArrayItem($arr){
		$numItems = count($arr);
		$rand = rand(0, $numItems-1);
		$item = $arr[$rand];
		return($item);
	}

	//Exclude js and return others js
	private static function excludeJS($key, $excludes)
	{
		$match = false;
		if ($excludes)
		{
			$excludes = explode(',', $excludes);
			foreach ($excludes as $exclude)
			{
				if (JFile::getName($key) == trim($exclude))
				{
					$match = true;
				}
			}
		}

		return $match;
	}

	public static function compressJS($excludes = '')
	{//function to compress js files

		require_once(__DIR__ . '/libs/Minifier.php');

		$doc       = JFactory::getDocument();
		$app       = JFactory::getApplication();
		$cachetime = $app->get('cachetime', 15);

		$all_scripts  = $doc->_scripts;
		$cache_path   = JPATH_CACHE . '/com_templates/templates/' . self::getTemplate();
		$scripts      = array();
		$root_url     = JURI::root(true);
		$minifiedCode = '';
		$md5sum       = '';

		//Check all local scripts
		foreach ($all_scripts as $key => $value)
		{
			$js_file = str_replace($root_url, JPATH_ROOT, $key);

			if (strpos($js_file, JPATH_ROOT) === false) {
				$js_file = JPATH_ROOT . $key;
			}

			if (JFile::exists($js_file))
			{
				if (!self::excludeJS($key, $excludes))
				{
					$scripts[] = $key;
					$md5sum .= md5($key);
					$compressed = \JShrink\Minifier::minify(JFile::read($js_file), array('flaggedComments' => false));
					$minifiedCode .= "/*------ " . JFile::getName($js_file) . " ------*/\n" . $compressed . "\n\n";//add file name to compressed JS

					unset($doc->_scripts[$key]); //Remove sripts
				}
			}
		}
		
		//Compress All scripts
		if ($minifiedCode)
		{
			if (!JFolder::exists($cache_path))
			{
				JFolder::create($cache_path, 0755);
			}
			else
			{

				$file = $cache_path . '/' . md5($md5sum) . '.js';

				if (!JFile::exists($file))
				{
					JFile::write($file, $minifiedCode);
				}
				else
				{
					if (filesize($file) == 0 || ((filemtime($file) + $cachetime * 60) < time()))
					{
						JFile::write($file, $minifiedCode);
					}
				}

				$doc->addScript(JURI::base(true) . '/cache/com_templates/templates/' . self::getTemplate() . '/' . md5($md5sum) . '.js');
			}
		}

		return;
	}

	
	//Compress CSS files
	public static function compressCSS()
	{//function to compress css files

		require_once(__DIR__ . '/libs/cssmin.php');

		$doc             = JFactory::getDocument();
		$app             = JFactory::getApplication();
		$cachetime       = $app->get('cachetime', 15);
		$all_stylesheets = $doc->_styleSheets;
		$cache_path      = JPATH_CACHE . '/com_templates/templates/' . self::getTemplate();
		$stylesheets     = array();
		$root_url        = JURI::root(true);
		$minifiedCode    = '';
		$md5sum          = '';

		//Check all local stylesheets
		foreach ($all_stylesheets as $key => $value)
		{
			$css_file = str_replace($root_url, JPATH_ROOT, $key);

			if (strpos($css_file, JPATH_ROOT) === false) {
				$css_file = JPATH_ROOT . $key;
			}

			global $absolute_url;
			$absolute_url = $key;//absoulte path of each css file

			if (JFile::exists($css_file))
			{
				$stylesheets[] = $key;
				$md5sum .= md5($key);
				$compressed = CSSMinify::process(JFile::read($css_file));

				$fixUrl = preg_replace_callback('/url\(([^\)]*)\)/',
				function ($matches)
				{
					$url = str_replace(array('"', '\''), '', $matches[1]);

					global $absolute_url;
					$base = dirname($absolute_url);
					while (preg_match('/^\.\.\//', $url))
					{
						$base = dirname($base);
						$url  = substr($url, 3);
					}
					$url = $base . '/' . $url;

					return "url('$url')";
				}, $compressed);

				$minifiedCode .= "/*------ " . JFile::getName($css_file) . " ------*/\n" . $fixUrl . "\n\n";//add file name to compressed css

				unset($doc->_styleSheets[$key]); //Remove sripts
			}
		}

		//Compress All stylesheets
		if ($minifiedCode)
		{
			if (!JFolder::exists($cache_path))
			{
				JFolder::create($cache_path, 0755);
			}
			else
			{

				$file = $cache_path . '/' . md5($md5sum) . '.css';

				if (!JFile::exists($file))
				{
					JFile::write($file, $minifiedCode);
				}
				else
				{
					if (filesize($file) == 0 || ((filemtime($file) + $cachetime * 60) < time()))
					{
						JFile::write($file, $minifiedCode);
					}
				}

				$doc->addStylesheet(JURI::base(true) . '/cache/com_templates/templates/' . self::getTemplate() . '/' . md5($md5sum) . '.css');
			}
		}

		return;
	}

	//Dx Methods
	/**
	 */
	public static function getACFFiles($id, $FieldTotalAddress, $fileIndex=-1, $getCount=false, $getObject = false, $ul_class='com_nasr_report_files', $DLTitle="دانلود")
    {
        //getACFFiles($id,'com_nasr.project/NasrModel/Project/mafasa',-1,true)
        $context="";$tblPrefix="";$model="";$Field_name="";
        $uploaded_files;
        $FieldAddress = explode("/",$FieldTotalAddress);
                
        $context			= $FieldAddress[0];
        $tblPrefix			= $FieldAddress[1];
        $model				= $FieldAddress[2];
        $Field_name     	= $FieldAddress[3];

        $res='';
        $item = JModelLegacy::getInstance($model, $tblPrefix)->getItem($id);
        $fields = FieldsHelper::getFields($context, $item);
        
        
        
        foreach($fields as $field)
        {
            if($field->type=='acfupload' && $field->name==$Field_name)
            {
                $uploaded_files=$field;
                break;
            }
        }
        
        $base = $uploaded_files->fieldparams->get('upload_folder');
        
        $files =  $uploaded_files->value;
        if($getObject)
        {
			for ($i=0;$i<=(count($files)-1);$i++)
				{
					$files[$i]=JURI::root().$base.'/'.$files[$i];
				}
				
				return $files;
        }
		if($getCount)
		{
			return count($files);
		}
        if($fileIndex== -1 && !empty($files)){
            $res .='<ul class="'.$ul_class.'">';
            if (is_array($files)){            
                foreach ($files as $key => $value)
                    {
                        $res .= '<li><a href="'.JURI::root().$base.'/'.$value.'" download>'.$value.'</a></li>';
                    }
                    
            }
            else{
              $res .= '<li><a href="'.JURI::root().$base.'/'.$files.'" download>'.$files.'</a></li>';
            }
            $res .='</ul>';

            
            return $res;
            
        }
        else{
            if (is_array($files)){    
            die();
                foreach ($files as $key => $value)
                    {
                        if($key == $fileIndex)
                        return JURI::root().$base.'/'.$value;
                    }
                    
            }
            elseif(!empty($files))
            {
                return JURI::root().$base.'/'.$files;
            }
            else{
                return '#';
            }
        }
        
    }
	
	public static function SetTemplate($tmpl)
	{
		$app = JFactory::getApplication();
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')
		->from('`#__template_styles`');
		if(is_numeric($tmpl))
			$query->where('`id`= '.$tmpl);
		else
			$query->where('`template`= '.$db->quote($tmpl));
		
		$db->setQuery($query);
		
		$row = $db->loadObject();
		if($row)		
			$app->setTemplate($row->template, (new JRegistry($row->params)));
		
		else
			return 0;
	}


	/* Database Methodes */
		/**
	 * Get Database Default Settings
	 */
	static function getDBInstance($driver = null, $host = null, $user = null, $password = null, $dbname = null, $prefix = null) {
		$app = JFactory::getApplication();
		$params = JComponentHelper::getParams('com_jmm');
		$dbsettings = $params -> get('dbsettings');
		if ($dbsettings == 1)
		{
			$driver = $app -> getCfg('dbtype');
			$host = $params -> get('dbhost');
			$user = $params -> get('dbusername');
			$password = $params -> get('dbpass');
			if (isset($_REQUEST['dbname'])) {
				$dbname = JRequest::getVar('dbname');
			} else {
				$dbname = $params -> get('dbname');
			}
			$prefix = $params -> get('dbprefix');
		} 
		else
		{
			if (!isset($driver)) {
				$driver = $app -> getCfg('dbtype');
			}
			if (!isset($host)) {
				$host = $app -> getCfg('host');
			}
			if (!isset($user)) {
				$user = $app -> getCfg('user');
			}
			if (!isset($password)) {
				$password = $app -> getCfg('password');
			}
			if (!isset($dbname)) {
				if (isset($_REQUEST['dbname'])) {
					$dbname = JRequest::getVar('dbname');
				} else {
					$dbname = $app -> getCfg('db');
				}

			}
			if (!isset($prefix)) {
				$prefix = $app -> getCfg('dbprefix');
			}
		}
		/**
		 * If User Use Custom DB Configuration
		 */
		$option = array();
		$option['driver'] = $driver;
		$option['host'] = $host;
		$option['user'] = $user;
		$option['password'] = $password;
		$option['database'] = $dbname;
		$option['prefix'] = $prefix;
		$db = JDatabase::getInstance($option);

		if ($dbname == '') {
			$dbLists=self::getDataBaseLists($db);
			if(count($dbLists)>0){
				JFactory::getApplication() -> redirect('index.php?option=com_jmm&view=tables&dbname='.$dbLists[0],'DataBase Switched to '.$dbLists[0]);
			}
			
		}
		return $db;

	}

	/**
	 * every thing will be ok and fun!
	 * thanks       :)
	 */

	 
	/**
	 * Get Tables From Database
	 */
	static function getTablesFromDB($db = null) {
		if (!isset($db)) {
			$db = Dx::getDBInstance();
		}
		$query = "SHOW TABLE STATUS";
		$db -> setQuery($query);
		$rows = $db -> loadAssocList();
		$cols = array();
		foreach ($rows as &$row) {
			$cols[] = $row['Name'];
		}
		return $cols;
	}

	/**
	 * Get Cloumn Lists From Tablename
	 */
	static function getCloumnsFromTable($table, $db = null) {
		if (!isset($db)) {
			$db = Dx::getDBInstance();
		}
		$query = "SHOW COLUMNS FROM `$table`";
		$db -> setQuery($query);
		$rows = $db -> loadAssocList();
		$cols = array();
		foreach ($rows as &$row) {
			$cols[] = $row['Field'];
		}
		return $cols;
	}

	/**
	 * List Databases
	 */
	static function getDataBaseLists($db = null) {
		if (!isset($db)) {
			$db = Dx::getDBInstance();
		}
		$query = "SHOW DATABASES";
		$db -> setQuery($query);
		$rows = $db -> loadAssocList();
		$database = array();
		for ($i = 0; $i < count($rows); $i++) {
			$row = &$rows[$i];
			foreach ($row as $key => &$val) {
				$database[] = $val;
			}
		}
		return $database;
	}

	/**
	 * Show Table Structure
	 */

	static function getTableStructure($table=null, $db = null) {
		if (!isset($db)) {
			$db = Dx::getDBInstance();
		}
		if(!isset($table)){
			return false;
		}
		$query = "DESC $table";
		$db -> setQuery($query);
		$rows = $db -> loadAssocList();
		//check for test results
		/*
		for ($i = 0; $i < count($rows); $i++) {
			$row = &$rows[$i];
			foreach ($row as $key => &$val) {
				$row['Browse']='<a href="index.php?option=com_jmm&view=tables&action=structure&&tbl='.$val.'">Edit</a>';
				$row['Structure']='<a href="index.php?option=com_jmm&view=tables&action=browse&tbl='.$val.'">Delete</a>';
			}
		}
		*/
		return $rows;
	}

	/**
	 * Display Data From Table
	 */
	static function getDataFromTable($table, $db = null) {
		if (!isset($db)) {
			$db = Dx::getDBInstance();
		}
		$query = "SELECT * FROM $table";
		$db -> setQuery($query);
		$rows = $db -> loadAssocList();
		//check for test results
		/*
		for ($i = 0; $i < count($rows); $i++) {
			$row = &$rows[$i];
			foreach ($row as $key => &$val) {
				$row['Browse']='<a href="index.php?option=com_jmm&view=tables&action=structure&&tbl='.$val.'">Edit</a>';
				$row['Structure']='<a href="index.php?option=com_jmm&view=tables&action=browse&tbl='.$val.'">Delete</a>';
			}
		}
		*/
		return $rows;
	}
	public static function getXbyID($tbl, $conds = null, $list=true, $getCount=false, $db = null)
	{
		if (!isset($db)) {
			$db = Dx::getDBInstance();
		}
		$query = $db->getQuery(true);
		
		$query->select(($getCount)? 'COUNT(*) as count' :'a.*')->from('#__'.$tbl.' AS a');
		if($getCount) $list=false;
		
		if(true)
		{
			try
			{	
				
				foreach ($conds as $key => $value)
				{
					$operators  = ['<', '>', '='];
					$operated = false;
					foreach($operators as $operator)
					{
						if (substr($key,-1)==$operator) // Beginning of $aString matches element of $array
						{
							$query->where('a.'.$key.' '.($value) );
							$operated = true;
						}

					}
					if(!$operated) 	$query->where('a.'.$key.' ='.($value) );					
				}
				$db->setQuery($query);	
				if($list)
					return $db->loadObjectList();
				else
					return $getCount? $db->loadObject()->count : $db->loadObject();
			}
			catch(Exception $e)
			{
				return $e->getMessage();
			}
		}
	
	}

	public static function insertXLS($data, $import_schema, $tbl = null, $db = null)
	{
		if (!isset($db)) {
			$db = Dx::getDBInstance();
		}
			$query = $db->getQuery(true);

			$columns=[];
			$tbl_cols=[];
			$col_index=0;
			foreach($import_schema as $key => $val){
				if(!empty($val->attr[0]->xlsFields))
				{
				 	$tbl_cols[]=$val->attr[0]->colName;
				 	$columns[$col_index]->name=$val->attr[0]->colName;
					$columns[$col_index]->labels=explode(',',$val->attr[0]->xlsFields);
					$col_index++;
				}
			}
			$rows = array();
			$str = '';
			$q = 0;
			$len = count($data);
			foreach($data as $row)
			{
				
				$str .= '(';
				$k = 0;
				for($i=0;$i<=count($columns);$i++)
				{
					$col_fields = '';
					$first_label=true;
					for($j=0;$j<=count($columns[$i]->labels);$j++)
					{ 
						if($first_label){
						$col_fields .= $row[$columns[$i]->labels[$j]];
						}
						else 
							$col_fields .= ' - '.$row[$columns[$i]->labels[$j]];
						$first_label=false;
					}
					if($col_fields) 
					{
						$str.=$db->quote($col_fields);
					}
					if ($k >= count($columns)-1 )
						$str.='';
					else 
						$str.=',';

					$k++;
				}
				if ($q == $len - 1)
					$str.=')';
				else 
					$str.='),';

				$q++;
			}
			
			if(isset($tbl))
			{
				$query
					->insert($tbl)
					->columns($db->quoteName($tbl_cols))
					->values(
					substr($str, 1, -1) //implode(',', $rows)
					);
			}
			else 
			{
				return 'No table defined for Insert';
			}
			//die( $query);
			$db->setQuery($query);
		return	$db->execute();
	}

	public static function getActiveStyle()
	{
		$styleid = JMenu::getInstance('site')->getActive()->template_style_id;
		if($styleid == 0)
		{
			return self::getXbyID('template_styles',array('client_id' => '0','home'=>'1'),false)->id;
		}
		else
		return $styleid;
	}
	

	public static function UniteGallery_Json2Html($string)
	{
		$baseurl = JUri::base();
		$obj = json_decode($string,true);
		$res="";	
		if (is_array($obj) || is_object($obj))
		foreach($obj as $key => $value) 
		{			
			$res .= '
			 <img alt="'. $value['alt'].'"
			 src="'.$value['src'].'"
			 data-image="'.$baseurl.$value['src'].'"
			 data-description="'.$value['description'].'"/>
			';			
		}
		echo $res;
	}	


	public static function xls2json($filename, $justLabels=false)
    {
		require_once __DIR__ . '/libs/PHPExcel/PHPExcel.php';
        try {
            	

            $fileType	= PHPExcel_IOFactory::identify($filename);
            $reader		= PHPExcel_IOFactory::createReader($fileType);
            $content	= $reader->load($filename);
            $data		= $content->getActiveSheet()->toArray(null, true, true, false);
			
			$count		= count($data)-1;
			$labels		= array_shift($data);
			
			$keys		= array();
            $results	= array();
			if($justLabels) return $labels;
			foreach ($labels as $label)
			{
				$keys[] = $label;
			}
           // Add Ids, just in case we want them later
            $keys[] = 'id';
            for ($i = 0; $i < $count; $i++) 
			{
                $data[$i][] = $i;
            }
			
            for ($j = 0; $j < $count; $j++)
			{
                $d = array_combine($keys, $data[$j]);
                $results[$j] = $d;
            }
			
			return $results;
        }
		catch (Exception $e) {return $e->getMessage();}
		
	}

	public static function spreadsheet2json($filename, $justLabels=false)
    {
		require_once __DIR__ . '/libs/vendor/autoload.php';
		
        try {
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filename);
			$reader->setReadDataOnly(TRUE);
			$spreadsheet = $reader->load($filename);
			$data = $spreadsheet->getActiveSheet()->toArray();

			$count		= count($data)-1;
			$labels		= array_shift($data);
			
			$keys		= array();
            $results	= array();
			if($justLabels) return $labels;
			foreach ($labels as $label)
			{
				$keys[] = $label;
			}
           // Add Ids, just in case we want them later
            $keys[] = 'id';
            for ($i = 0; $i < $count; $i++) 
			{
                $data[$i][] = $i;
            }
			
            for ($j = 0; $j < $count; $j++)
			{
                $d = array_combine($keys, $data[$j]);
                $results[$j] = $d;
            }
			
			return $results;
        }
		catch (Exception $e) {return $e->getMessage();}
		
	}
	
	public static function getArticlePageURL($item)
	{
		JLoader::import('joomla.application.component.model');
		require_once JPATH_SITE . '/components/com_content/helpers/route.php';
		JModelLegacy::addIncludePath(JPATH_SITE.'/components/com_content/models','ContentModel');

		if(!empty($item) && self::getXbyID('content',array('id' => $item,'state'=>'1'),false)->id)
		{
			$article = JModelLegacy::getInstance('Article', 'ContentModel')->getItem($item);
			return JRoute::_(ContentHelperRoute::getArticleRoute($item , $article->catid, $article->language));
		}
		
		else return '#';
	
	}
/*
	public static function test_Transaction(){

		$db = JFactory::getDbo();
		try
		{
			$query = $db->getQuery(true);
			$values = array($db->quote('TEST_CONSTANT'), $db->quote('Custom'), $db->quote('/path/to/translation.ini'));
			$query->insert($db->quoteName('#__overridera'));
			$query->columns($db->quoteName(array('constant', 'string', 'file')));
			$query->values(implode(',',$values));

			$db->setQuery($query);
			$result = $db->execute();
		//	$db->transactionCommit();
		}
		catch (Exception $e)
		{
			// catch any database errors.
			//$db->transactionRollback();
			JErrorPage::render($e);
		}

		
	}*/
	/**********      
	 * Not sure about this functions
	 * ***************/
	//generate json for reports calendar
	public static function EventsJson($string)
	{
		$obj = json_decode($string,true);
		$res="";	
		if (is_array($obj) || is_object($obj))
		foreach($obj as $key => $value) 
		{	
			$res .= '{"date":"'.JHTML::_('date', $value['date'], JText::_('DATE_FORMAT_LC3')).'","content":"'.$value['content'].'"},';			
		}
		echo $res;
	}

	
	private static function d($data){
		
		if(is_null($data)){
			$str = "<i>NULL</i>";
		}elseif($data == ""){
			$str = "<i>Empty</i>";
		}elseif(is_array($data)){
			if(count($data) == 0){
				$str = "<i>Empty array.</i>";
			}else{
				$str = "<table class=\"table table-hover table-striped table-bordered\"  cellpadding=\"0\" cellspacing=\"0\"><td class='objType ' colspan=\"3\">"
				.gettype($data)."</td>";
				$str .= "";
				foreach ($data as $key => $value) {
					$str .= "<tr><td style=\"border:1px solid #000;\">". $key 
					. "</td><td style=\"border:1px solid #000;\">" . self::d($value) . "</td>"
					. "</td><td class='vType' style=\"border:1px solid #000;\">" . gettype($value) . "</td></tr>";
				}
				$str .= "</table>";
			}
		}elseif(is_resource($data)){
			while($arr = mysql_fetch_array($data)){
				$data_array[] = $arr;
			}
			$str = self::d($data_array);
		}elseif(is_object($data)){
			$str = self::d(get_object_vars($data));
		}elseif(is_bool($data)){
			$str = "<i>" . ($data ? "True" : "False") . "</i>";
		}else{
			$str = $data;
			$str = preg_replace("/\n/", "<br>\n", $str);
		}
		return $str;
	}
	
	public static function dnl($data){		
		echo self::d($data) . "<br>\n";
	}
	
	public static function dd($data){
		echo self::dnl($data);
		exit;
	}
	
	public static function ddt($message = ""){
		echo "[" . date("Y/m/d H:i:s") . "]" . $message . "<br>\n";
	}

 
	public static function html2menu($menuid,$html)
	{
		$app		= JFactory::getApplication();
        $menu		= $app->getMenu();
		//self::dd($menu->getActive());
        if ($app->isSite() && $menu->getActive()->id == $menuid) 
		{
			$file = file_get_contents(JUri::base().DIRECTORY_SEPARATOR.$html);
			echo ($file);
			jexit();
        }
	}

	public static function page2html($page,$dst)
	{
        if (self::url_exists($page)) 
			return copy($page, getcwd().DIRECTORY_SEPARATOR.$dst);

	}

	public static function url_exists($url)
	{
		$file_headers = @get_headers($url);
		if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') 
		{
			return false;
		}
		else 
		{
			return true;
		}
	}


	function json2span($string)
	{
		$obj = json_decode($string, TRUE);
		$res;
		foreach($obj as $key => $value) 
		{
			foreach($value as $data => $val)
			{	
				if(!empty(implode(',', $val))){
				$res .= '<span style="margin:2px;width:100%" class="label label-danger"><span class="label label-info">'.implode(' | ', $val).'</span>';
				if(!empty($value['hour']))
				{
					$res .=' ' .$value['hour'];
					/*$H=$value['hour']; ---//(!empty($H))?   $res.=' '. $H : $res .='';	*/
				}
				}
				$res.= '</span>';
			}
		}
		return $res;	
}
/*
	$object = json_decode($this->params->get('search_replace'), true);
	$res=[];	$i=0;	$j=0;
	foreach($object as $key => $items)
	{
		foreach($items as $item => $val)
		{
			$res[$item][$key]=$val;					
		}
	}
	die(json_encode(($res)));
*/
		
}
