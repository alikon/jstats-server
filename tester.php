<?php
/**
 * @package    Joomla.Cli
 *
 * @copyright  Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This is a script which should be called from the command-line
 * For example something like:
 * /usr/bin/php /path/to/site/cli/tester.php -n 1000000 -i 10000 -t redis
 */
 // Set flag that this is a parent file.
const _JEXEC = 1;

error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
	require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_LIBRARIES . '/import.legacy.php';
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

 class TesterCli extends JApplicationCli
{
	/*
	 * Start time for the benchmark process
	 *
	 * @var    string
	 * @since  3.5
	 */
	 
	private $time = null;
	/**
	 * Start time for each batch
	 *
	 * @var    string
	 * @since  2.5
	 */
	private $qtime = null;
	private $redis = null;
 	/**
	 * Entry point for Tester CLI script
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	public function doExecute()
	{
		// Print a blank line.
		$args = (array) $GLOBALS['argv'];
		
		$db = JFactory::getDbo();

		$this->out('============================');

		// Initialize the time value.
		$this->time = microtime(true);

		// Remove the script time limit.
		@set_time_limit(0);

		// Fool the system into thinking we are running as JSite.
		$_SERVER['HTTP_HOST'] = 'domain.com';
		JFactory::getApplication('site');
		$conf = JFactory::getConfig();
		JLoader::register('JRedis', JPATH_PLATFORM . '/joomla/database/redis.php');
		$options = array(
				'host'   => $conf->get('redis_server_host', 'localhost'),
				'port'   => $conf->get('redis_server_port', 6379),
				'auth'   => $conf->get('redis_server_auth', null),
				'db'     => $conf->get('redis_server_db', 0),
				'driver' => 'redis',
			);
		$this->redis = JRedis::getInstance($options);
		$count = $this->redis->dbSize();
		$this->out('Redis size' . $count);

		//$this->redis->flushDb();
		$c =$args[2];
		// Process the benchmark.
		
		//$csv = fopen('php://output', 'w');
		//$csv = fopen('file10k.csv', 'w+');
		// Set the batch start time.
		$phpversion=array('5.5.9','5.6.0','5.7.0', '6.0.0');
		$cmsversion=array('3.4.9','3.3.0','3.2.0', '3.3.4','3.3.3');
		$dbtype=array('Mysql','PostgreSql','MS SQL');
		$ostype=array('Linux','Windows','Os/X');
		$this->qtime = microtime(true);
		for ($i = 1; $i <= $c; $i++)
		{
			$data = array(				
				'unique_id' => md5(uniqid(rand(), true)),
				//'unique_id' => $i,
				'php_version' => $phpversion[array_rand($phpversion)],
				'db_type' => $dbtype[array_rand($dbtype)],
				'db_version' => JFactory::getDbo()->getVersion(),
				'cms_version' => $cmsversion[array_rand($cmsversion)],
				'server_os' => $ostype[array_rand($ostype)]
			);
			
			
			
		//jexit(var_dump($args[4]));
		switch ($args[6])
		{
			case  '0': 
				$this->saveApp((object)$data);
				break;
			case  'exp': 
				$this->saveExp((object)$data);
				break;
			case  'alikon':
				$this->saveAlikon((object)$data);
				break;
			case  'redis':
				$this->saveRedis($data);
				break;  
			case  'old':
				$this->saveOld((object)$data);
				break; 	
			default:   
				echo '[WARNING] Unknown parameter'."\n" ;
				jexit('[WARNING]');
				break;
		}	
			
			// Batch reporting.
			//$this->out('TESTER_CLI_BATCH_COMPLETE:' .($i + 1) . round(microtime(true) - $this->qtime, 3));
			if (( $i % $args[4]) == 0 )
			{	
				$this->out('TESTER_CLI_BATCH:' .$i.' : '. round(microtime(true) - $this->qtime, 3));
				$value = array($i, round(microtime(true) - $this->qtime, 3));
			//	fputcsv($csv,  $value);
				$this->qtime = microtime(true);
			}
		}
		// Total reporting.
		$this->out('============================');
		$this->out('TESTER_CLI_PROCESS_COMPLETE '. round(microtime(true) - $this->time, 3));
		//fclose($csv);
		// Print a blank line at the end.
		$this->out();
		
		$this->out('cms 3.2.0: '.$this->redis->get('cms_version:3.2.0'));
		$this->out('cms 3.3.0: '.$this->redis->get('cms_version:3.3.0'));
		$this->out('cms 3.4.9: '.$this->redis->get('cms_version:3.4.9'));
		$this->out('cms 3.3.4: '.$this->redis->get('cms_version:3.3.4'));
		$this->out('cms 3.3.3: '.$this->redis->get('cms_version:3.3.3'));
		$this->out('os Windows: '.$this->redis->get('server_os:Windows'));
		$this->out('os Linux: '.$this->redis->get('server_os:Linux'));
		$this->out('os Os/X: '.$this->redis->get('server_os:Os/X'));
		$this->out('db Mysql: '.$this->redis->get('db_type:Mysql'));
		$this->out('db PostgreSql: '.$this->redis->get('db_type:PostgreSql'));
		$this->out('db MS SQL: '.$this->redis->get('db_type:MS SQL'));
		$count = $this->redis->dbSize();
		$this->out('Redis size' . $count);
		
	
		

	}
	/**
	 * Saves the given data.
	 *
	 * @param   \stdClass  $data  Data object to save.
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function saveExp($data)
	{
		//jexit(var_dump($data));
		$db = JFactory::getDbo();
		$recordExists = false;
		// Check if a row exists for this unique ID and update the existing record if so
		try
		{
		  $recordExists = $db->insertObject('#__jstatsa', $data, ['unique_id']);
		
	  }
	  catch (RuntimeException $e)
	 	{
			// a row exists for this unique ID
			//  jexit(var_dump($recordExists));
			$recordExists = true;
		}
		/*
		$query = $db->getQuery(true);
		$query
			->select('id')
			->from('#__jstats0')
			->where('unique_id = ' . $db->quote($data->unique_id));
		$recordExists = $db->setQuery($query)->loadResult();
		*/
		if ($recordExists)
		{
			//$data->id = $recordExists;
			$db->updateObject('#__jstatsa', $data, ['unique_id']);
		}
		/*
		else
		{
			$db->insertObject('#__jstats0', $data, ['id']);
		}
		*/
	}
	public function saveAlikon($data)
	{
		//var_dump($data);
		$db = JFactory::getDbo();
		// Check if a row exists for this unique ID and update the existing record if so
		$query = $db->getQuery(true);
		$query
			->select('unique_id')
			->from('#__jstatsb')
			->where('unique_id = ' . $db->quote($data->unique_id));
		$recordExists = $db->setQuery($query)->loadResult();
		
		if ($recordExists)
		{
			$data->unique_id = $recordExists;
			$db->updateObject('#__jstatsb', $data, ['unique_id']);
		}
		else
		{
			$db->insertObject('#__jstatsb', $data, ['unique_id']);
		}
	}
	public function getItems()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query
			->select('*')
			->from('#__jstats');
			//->group('unique_id');
		return $db->setQuery($query)->loadObjectList();
	}
	public function saveRedis($data)
	{
		if ($upd = $this->redis->hgetAll('uid:'.$data['unique_id']))
		{
			//echo 'update';
			if($upd['php_version'] != $data['php_version'])
			{
				$this->redis->multi()
					->incr('php_version:'.$data['php_version'])
					->incr('cms_version:'.$data['cms_version'].':php_version:'.$data['php_version'])
					->incr('cms_version:'.$data['cms_version'].':php_version:'.$data['php_version'].':db_type:'.$data['db_type'])
					->decr('php_version:'.$upd['php_version'])
					->decr('cms_version:'.$upd['cms_version'].':php_version:'.$upd['php_version'])
					->decr('cms_version:'.$upd['cms_version'].':php_version:'.$upd['php_version'].':db_type:'.$upd['db_type'])
					->exec();
				
			}
			if($upd['db_type'] != $data['db_type'])
			{
				$this->redis->multi()
					->incr('db_type:'.$data['db_type'])
					->incr('cms_version:'.$data['cms_version'].':db_type:'.$data['db_type'])
					->incr('cms_version:'.$data['cms_version'].':php_version:'.$data['php_version'].':db_type:'.$data['db_type'])
					->decr('db_type:'.$upd['db_type'])
					->decr('cms_version:'.$upd['cms_version'].':db_type:'.$upd['db_type'])
					->decr('cms_version:'.$upd['cms_version'].':php_version:'.$upd['php_version'].':db_type:'.$upd['db_type'])
					->exec();
				
			}
			if($upd['server_os'] != $data['server_os'])
			{
				$this->redis->multi()
					->incr('server_os:'.$data['server_os'])
					->incr('cms_version:'.$data['cms_version'].':server_os:'.$data['server_os'])
					->decr('server_os:'.$upd['server_os'])
					->decr('cms_version:'.$upd['cms_version'].':server_os:'.$upd['server_os'])
					->exec();
				
			}
			$this->redis->hmset('uid:'.$data['unique_id'], $data);
		}
		else
		{
			//echo 'insert';
			$this->redis->multi()
				->hmset('uid:'.$data['unique_id'], $data)
				->incr('php_version:'.$data['php_version'])
				->incr('db_type:'.$data['db_type'])
				->incr('server_os:'.$data['server_os'])
				->incr('cms_version:'.$data['cms_version'])
				->incr('cms_version:'.$data['cms_version'].':php_version:'.$data['php_version'])
				->incr('cms_version:'.$data['cms_version'].':server_os:'.$data['server_os'])
				->incr('cms_version:'.$data['cms_version'].':db_type:'.$data['db_type'])
				->incr('cms_version:'.$data['cms_version'].':php_version:'.$data['php_version'].':db_type:'.$data['db_type'])
				->exec();
		}
		
	}
	public function saveApp($data)
	{
		$http = JHttpFactory::getHttp();
		$uri = new JUri('http://localhost/jstats-server/www/submit');
		
		//var_dump($data);
		try
		{
			// Don't let the request take longer than 2 seconds to avoid page timeout issues
			$status = $http->post($uri, $data, null, 2);
	//jexit(var_dump($status));
			if ($status->code === 200)
			{
				$ok = true;
			}
		}
		catch (UnexpectedValueException $e)
		{
			// There was an error sending stats. Should we do anything?
			JFactory::getApplication()->enqueueMessage($e->getMessage());
		}
		catch (RuntimeException $e)
		{
			// There was an error connecting to the server or in the post request
			JFactory::getApplication()->enqueueMessage($e->getMessage());
		}
	}
	public function saveOld($data)
	{
		//jexit('save');
		$db = JFactory::getDbo();

		// Check if a row exists for this unique ID and update the existing record if so
		$recordExists = $db->setQuery(
			$db->getQuery(true)
				->select('id')
				->from('#__jstats0')
				->where('unique_id = ' . $db->quote($data->unique_id))
		)->loadResult();

		if ($recordExists)
		{
			$data->id = $recordExists;
			$db->updateObject('#__jstats0', $data, ['id']);
		}
		else
		{
			$db->insertObject('#__jstats0', $data, ['id']);
		}
	}
	
}	
JApplicationCli::getInstance('TesterCli')->execute();
