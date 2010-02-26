<?php 
class EventCore extends Object
{
	/**
	 * Event objects
	 *
	 * @var array
	 */
	private $eventClasses = array();

	/**
	 * Available handlers and what eventclasses they appear in.
	 *
	 * @var array
	 */
	private $eventHandlerCache = array();	
	
	/**
	 * Returns a singleton instance of the EventCore class.
	 *
	 * @return EventCore instance
	 * @access public
	 */
	function &getInstance() {
		static $instance = array();
		if (!$instance) {
			$instance[0] =& new EventCore();
			$instance[0]->__loadEventHandlers();
		}
		return $instance[0];
	}

	/**
	 * Trigger an event or array of events
	 *
	 * @param string|array $eventName
	 * @param array $data (optional) Array of data to pass along to the event handler
	 * @return array
	 *
	 */
	public function trigger(&$HandlerObject, $eventName, $data = array())
	{
		if(!is_array($eventName))
		{
			$eventName = array($eventName);
		}

		$eventNames = Set::filter($eventName);
		foreach($eventNames as $eventName)
		{
			extract(EventCore::__parseEventName($eventName), EXTR_OVERWRITE);
			$return[$eventName] = EventCore::__dispatchEvent($HandlerObject, $scope, $event, $data);
		}

		return $return;
	}	
	
	/**
	 * Loads all available event handler classes for enabled plugins
	 *
	 */
	private function __loadEventHandlers()
	{
		App::import('Core', 'Folder');
		
		$folder = new Folder();
		
		$pluginsPaths = App::path('plugins');

		foreach($pluginsPaths as $pluginsPath)
		{
			$folder->cd($pluginsPath);
			$plugins = $folder->read();
			$plugins = $plugins[0];
			
			if(count($plugins))
			{
				foreach($plugins as $pluginName)
				{
					$filename = $pluginsPath . $pluginName . DS . $pluginName . '_events.php';
					$className = Inflector::camelize($pluginName . '_events');
					if(file_exists($filename))
					{
						if(EventCore::__loadEventClass($className, $filename))
							EventCore::__getAvailableHandlers($this->eventClasses[$className]);
					}
				}
			}
		}
	}	

	/**
	 * Dispatch Event
	 *
	 * @param string $eventName
	 * @param array $data (optional)
	 * @return array
	 *
	 */
	private function __dispatchEvent(&$HandlerObject, $scope, $eventName, $data = array())
	{
		$eventHandlerMethod = EventCore::__handlerMethodName($eventName);
		$_this =& EventCore::getInstance();
		
		$return = array();

		if(isset($_this->eventHandlerCache[$eventName]))
		{
			foreach($_this->eventHandlerCache[$eventName] as $eventClass)
			{
				$pluginName = EventCore::__extractPluginName($eventClass);
				if(isset($_this->eventClasses[$eventClass])
					&& is_object($_this->eventClasses[$eventClass])
					&& ($scope == 'Global' || $scope == $pluginName)
					)
				{
					$EventObject = $_this->eventClasses[$eventClass];

					$Event = new Event($eventName, &$HandlerObject, $pluginName, $data);
			
					$return[$pluginName] = call_user_func_array(array(&$EventObject, $eventHandlerMethod), array(&$Event));
				}
			}
		}

		return $return;
	}

	private function __parseEventName($eventName)
	{
		App::import('Core', 'String');
		
		$eventTokens = String::tokenize($eventName, '.');
		$scope = 'Global';
		$event = $eventTokens[0];
		if (count($eventTokens) > 1)
		{
			list($scope, $event) = $eventTokens;
		}
		return compact('scope', 'event');
	}

	/**
	 * Converts event name into a handler method name
	 *
	 * @param string $eventName
	 * @return string
	 *
	 */
	private function __handlerMethodName($eventName)
	{
		return 'on'.Inflector::camelize($eventName);
	}

	/**
	 * Loads list of available event handlers in a event object
	 *
	 * @param object $Event
	 *
	 */
	private function __getAvailableHandlers(&$Event)
	{
		if(is_object($Event))
		{
			$_this =& EventCore::getInstance();
			
			$availableMethods = get_class_methods($Event);
	
			foreach($availableMethods as $availableMethod)
			{
				if(strpos($availableMethod, 'on') === 0)
				{
					$handlerName = substr($availableMethod, 2);
					$handlerName{0} = strtolower($handlerName{0});
					$_this->eventHandlerCache[$handlerName][] = get_class($Event);
				}
			}
		}
	}

	/**
	 * Loads and initialises an event class
	 *
	 * @param string $className
	 * @param string $filename
	 *
	 */
	private function __loadEventClass($className, $filename)
	{
		App::Import('file', $className, true, array(), $filename);

		try
		{
			$_this =& EventCore::getInstance();
			
			$_this->eventClasses[$className] =& new $className();
			return true;
		}
		catch(Exception $e)
		{
			return false;
		}
	}

	/**
	 * Extracts the plugin name out of the class name
	 *
	 * @param string $className
	 * @return string
	 *
	 */
	private function __extractPluginName($className)
	{
		return substr($className, 0, strlen($className) - 6);
	}
}

/**
 * Event Object
 */
class Event {

	/**
	 * Contains assigned values
	 *
	 * @var array
	 */
	protected $values = array();

	/**
	 * Constructor with EventName and EventData (optional)
	 *
	 * Event Data is automaticly assigned as properties by array key
	 *
	 * @param string $eventName Name of the Event
	 * @param array $data optional array with k/v data
	 */
	public function __construct($eventName, &$HandlerObject, $pluginName, $data = array()) {
		$this->name = $eventName;
		$this->Controller = $HandlerObject;
		$this->plugin = $pluginName;

		if (!empty($data)) {
			foreach ($data as $name => $value) {
				$this->{$name} = $value;
			} // push data values to props
		}
	}

	/**
	 * Write to object
	 *
	 * @param string $name Key
	 * @param mixed $value Value
	 */
	public function __set($name, $value) {
		$this->values[$name] = $value;
	}

	/**
	 * Read from object
	 *
	 * @param string $name Key
	 */
	public function __get($name) {
		return $this->values[$name];
	}
}