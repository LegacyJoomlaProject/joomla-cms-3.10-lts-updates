<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Quickicon.eos310
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Joomla! end of support notification plugin
 *
 * @since  3.10.0
 */
class PlgQuickiconEos310 extends CMSPlugin
{
	/**
	 * The EOS date for 3.10
	 *
	 * @var    string
	 * @since  3.10.0
	 */
	static $EOS_DATE = '2023-08-17';

	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  3.10.0
	 */
	protected $app;

	/**
	 * Database object
	 *
	 * @var    DatabaseDriver
	 * @since  3.10.0
	 */
	protected $db;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.10.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Holding the current valid message to be shown
	 *
	 * @var    boolean
	 * @since  3.10.0
	 */
	private $currentMessage = false;


	/**
	 * Holding the current version string to be inserted into popup text
	 *
	 * @var    string
	 * @since  3.10.19
	 */	
	protected $versionString="";


	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * @since   3.10.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$this->versionString = \Joomla\CMS\Version::getShortVersion();
		// -- Check if there is a Legacy Joomla Project subscription key.
		$cparams = ComponentHelper::getParams( 'com_joomlaupdate' );
		$cls = get_class($cparams);
		$updturl = $cparams->get( 'customurl' );
		$webkey="";
		
		if($updturl){
		    if(stripos($updturl,"update.legacyjoomla.com")){
		        // Legacy joomla update
		        if($pos = stripos($updturl, "/update/")){
		            $webkey = substr($updturl,$pos+8,16);
		            if(strlen($webkey) == 16){
		                // Valid webkey .... check subscription date with server.
		                $subinfo = $this->ljpSubInfo($webkey);
		                PlgQuickiconEos310::$EOS_DATE = $subinfo['sub_end'];
                        $msg = $this->getLjpMessage($subinfo);
                        //$this->currentMessage = $msg;
                        //return;
		            }
		        }
		    } else {
		      // This is an unknown update channel.
    		    $msg = array(
    		        'id'            => 5,
    		        'messageText'   => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_EXT_SUPPORT',
    		        'quickiconText' => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_EXT_SUPPORT_SHORT',
    		        'messageType'   => 'warning',
    		        'image'         => 'warning-circle',
    		        'messageLink'   => $updturl,
    		        'groupText'     => 'PLG_QUICKICON_EOS310_GROUPNAME_EOS',
    		        'snoozable'     => true,
    		    );
		    }
		} else {
		    $diff           = Factory::getDate()->diff(Factory::getDate(PlgQuickiconEos310::$EOS_DATE));
		    $monthsUntilEOS = floor($diff->days / 30.417);
		    $msg = $this->getMessageInfo($monthsUntilEOS, $diff->invert);
		}		
		$this->currentMessage = $msg;
	}
	
	/**
	 * Contact the Legacy Joomla Project update server to check status of webkey subscription
	 *
	 * @param   string  $webkey  The subscription webkey
	 *
	 * @return  array|void  An associative array with subscription information or result error code 
	 *						from update server.
	 *
	 * @since   3.10.19-ljp
	 */
	function ljpSubInfo($webkey){
	    $ch = curl_init();
	    $url = "https://update.legacyjoomla.com/update.php?cmd=sub-info&webkey=$webkey";
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    //$headers = array();
	    //$headers[] = 'Content-Type: application/json';
	    //$headers[] = 'Authorization: Bearer ' . $this->access_token;
	    //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	    $json = curl_exec($ch);
	    $err = curl_error($ch);
	    curl_close($ch);
	    $result = json_decode($json, true);	    
	    return $result;
	}
	

	/**
	 * Check and show the the alert and quickicon message
	 *
	 * @param   string  $context  The calling context
	 *
	 * @return  array|void  A list of icon definition associative arrays, consisting of the
	 *			keys link, image, text and access, or void.
	 *
	 * @since   3.10.0
	 */
	public function onGetIcons($context)
	{
		if (!$this->shouldDisplayMessage())
		{
			return;
		}

		// No messages yet
		if (!$this->currentMessage)
		{
			return;
		}

		// Show this only when not snoozed
		if ($this->params->get('last_snoozed_id', 0) < $this->currentMessage['id'])
		{
			// Load the snooze scripts.
			HTMLHelper::_('jquery.framework');
			HTMLHelper::_('script', 'plg_quickicon_eos310/snooze.js', array('version' => 'auto', 'relative' => true));

			// Build the  message to be displayed in the cpanel
			if(array_key_exists('externalLink',$this->currentMessage)){
			    $messageText = Text::sprintf(
			        $this->currentMessage['messageText'],
			        HTMLHelper::_('date', PlgQuickiconEos310::$EOS_DATE, Text::_('DATE_FORMAT_LC3')),
			        $this->currentMessage['messageLink'],
			        $this->versionString,
			        $this->currentMessage['externalLink'],
			        );
			} else {
                $messageText = Text::sprintf(
    				$this->currentMessage['messageText'],
    			    HTMLHelper::_('date', PlgQuickiconEos310::$EOS_DATE, Text::_('DATE_FORMAT_LC3')),
    				$this->currentMessage['messageLink'],
                    $this->versionString
    			);
			}
			if ($this->currentMessage['snoozable'])
			{
				$messageText .=
					'<p><button class="btn btn-warning eosnotify-snooze-btn" type="button">' .
					Text::_('PLG_QUICKICON_EOS310_SNOOZE_BUTTON') .
					'</button></p>';
			}

			$this->app->enqueueMessage(
				$messageText,
				$this->currentMessage['messageType']
			);
		}

		// The message as quickicon
		$messageTextQuickIcon = Text::sprintf(
			$this->currentMessage['quickiconText'],
			HTMLHelper::_(
				'date',
			    PlgQuickiconEos310::$EOS_DATE,
				Text::_('DATE_FORMAT_LC3')
			)
		);

		// The message as quickicon
		return array(array(
			'link'   => $this->currentMessage['messageLink'],
			'target' => '_blank',
			'rel'    => 'noopener noreferrer',
			'image'  => $this->currentMessage['image'],
			'text'   => $messageTextQuickIcon,
			'id'	 => 'plg_quickicon_eos310',
			'group'  => $this->currentMessage['groupText'],
		));
	}

	/**
	 * User hit the snooze button
	 *
	 * @return  void
	 *
	 * @since   3.10.0
	 *
	 * @throws  JAccessExceptionNotallowed  If user is not allowed.
	 */
	public function onAjaxSnoozeEOS()
	{
		// No messages yet so nothing to snooze
		if (!$this->currentMessage)
		{
			return;
		}

		if (!$this->isAllowedUser() || !$this->isAjaxRequest())
		{
			throw new JAccessExceptionNotallowed(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
		}

		// Make sure only snoozable messages can be snoozed
		if ($this->currentMessage['snoozable'])
		{
			$this->params->set('last_snoozed_id', $this->currentMessage['id']);

			$this->saveParams();
		}
	}
	
	/**
	 * Return the text to be displayed based on subscription time remaining.
	 *
	 * @param   array  $subInfo  LJP Subscription information array.
	 *
	 * @return  array|bool  An array with the message to be displayed or false
	 *
	 * @since   3.10.19-ljp
	 */
	private function getLjpMessage($subInfo){
	    // The subscription is expired
	    if ($subInfo['sub_days_remain'] < 0)
	    {

	        return array(
	            'id'            => 5,
	            'messageText'   => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_ELTS_SUPPORT_ENDED',
	            'quickiconText' => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_ELTS_SUPPORT_ENDED_SHORT',
	            'messageType'   => 'error',
	            'image'         => 'minus-circle',
	            'messageLink'   => 'https://legacyjoomla.com',
	            'externalLink'  => 'https://github.com/LegacyJoomlaProject/joomla-cms-3.10-lts-updates',
	            'groupText'     => 'PLG_QUICKICON_EOS310_GROUPNAME_EOS',
	            'snoozable'     => false,
	        );

	        
	    } 
	    if ($subInfo['sub_days_remain'] < 31) {
	        return array(
	            'id'            => 4,
	            'messageText'   => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_ELTS_SUPPORT_ENDING',
	            'quickiconText' => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_ELTS_SUPPORT_ENDING_SHORT',
	            'messageType'   => 'warning',
	            'image'         => 'warning-circle',
	            'messageLink'   => 'https://legacyjoomla.com',
	            'groupText'     => 'PLG_QUICKICON_EOS310_GROUPNAME_EOS',
	            'snoozable'     => false,
	        );
	    }
	    return array(
	        'id'            => 1,
	        'messageText'   => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_ELTS_SUPPORT',
	        'quickiconText' => 'PLG_QUICKICON_EOS310_MESSAGE_WARNING_ELTS_SUPPORT_SHORT',
	        'messageType'   => 'info',
	        'image'         => 'info-circle',
	        'messageLink'   => 'https://docs.joomla.org/Special:MyLanguage/Planning_for_Mini-Migration_-_Joomla_3.10.x_to_4.x',
	        'groupText'     => 'PLG_QUICKICON_EOS310_GROUPNAME_EOS',
	        'snoozable'     => true,
	    );
	}

	/**
	 * Return the EOS text to be displayed for manually-installed package without update key 
	 *
	 * @param   integer  $monthsUntilEOS  The months until we reach EOS
	 * @param   integer  $inverted        Have we surpassed the EOS date
	 *
	 * @return  array|bool  An array with the message to be displayed or false
	 *
	 * @since   3.10.0
	 */
	private function getMessageInfo($monthsUntilEOS, $inverted)
	{
		// The EOS date has passed - Support has ended
		if ($inverted === 1)
		{
			return array(
				'id'            => 5,
				'messageText'   => 'PLG_QUICKICON_EOS310_MESSAGE_ERROR_SUPPORT_ENDED',
				'quickiconText' => 'PLG_QUICKICON_EOS310_MESSAGE_ERROR_SUPPORT_ENDED_SHORT',
			    'messageType'   => 'warning',
			    'image'         => 'warning-circle',
			    'messageLink'   => 'https://legacyjoomla.com',
			    'externalLink'  => 'https://github.com/LegacyJoomlaProject/joomla-cms-3.10-lts-updates',
				'groupText'     => 'PLG_QUICKICON_EOS310_GROUPNAME_EOS',
				'snoozable'     => false,
			);
		}
		return false;
	}

	/**
	 * Determines if the message and quickicon should be displayed
	 *
	 * @return  boolean
	 *
	 * @since   3.10.0
	 */
	private function shouldDisplayMessage()
	{
		// Only on admin app
		if (!$this->app->isClient('administrator'))
		{
			return false;
		}

		// Only if authenticated
		if (Factory::getUser()->guest)
		{
			return false;
		}

		// Only on HTML documents
		if ($this->app->getDocument()->getType() !== 'html')
		{
			return false;
		}

		// Only on full page requests
		if ($this->app->input->getCmd('tmpl', 'index') === 'component')
		{
			return false;
		}

		// Only to com_cpanel
		if ($this->app->input->get('option') !== 'com_cpanel')
		{
			return false;
		}

		// Don't show anything in 4.0
		if (version_compare(JVERSION, '4.0', '>='))
		{
			return false;
		}

		return true;
	}

	/**
	 * Check valid AJAX request
	 *
	 * @return  boolean
	 *
	 * @since   3.10.0
	 */
	private function isAjaxRequest()
	{
		return strtolower($this->app->input->server->get('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
	}

	/**
	 * Check if current user is allowed to send the data
	 *
	 * @return  boolean
	 *
	 * @since   3.10.0
	 */
	private function isAllowedUser()
	{
		return Factory::getUser()->authorise('core.login.admin');
	}

	/**
	 * Save the plugin parameters
	 *
	 * @return  boolean
	 *
	 * @since   3.10.0
	 */
	private function saveParams()
	{
		$query = $this->db->getQuery(true)
			->update($this->db->quoteName('#__extensions'))
			->set($this->db->quoteName('params') . ' = ' . $this->db->quote($this->params->toString('JSON')))
			->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
			->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('quickicon'))
			->where($this->db->quoteName('element') . ' = ' . $this->db->quote('eos310'));

		try
		{
			// Lock the tables to prevent multiple plugin executions causing a race condition
			$this->db->lockTable('#__extensions');
		}
		catch (Exception $e)
		{
			// If we can't lock the tables it's too risky to continue execution
			return false;
		}

		try
		{
			// Update the plugin parameters
			$result = $this->db->setQuery($query)->execute();

			$this->clearCacheGroups(array('com_plugins'), array(0, 1));
		}
		catch (Exception $exc)
		{
			// If we failed to execute
			$this->db->unlockTables();

			$result = false;
		}

		try
		{
			// Unlock the tables after writing
			$this->db->unlockTables();
		}
		catch (Exception $e)
		{
			// If we can't lock the tables assume we have somehow failed
			$result = false;
		}

		return $result;
	}

	/**
	 * Clears cache groups. We use it to clear the plugins cache after we update the last run timestamp.
	 *
	 * @param   array  $clearGroups   The cache groups to clean
	 * @param   array  $cacheClients  The cache clients (site, admin) to clean
	 *
	 * @return  void
	 *
	 * @since   3.10.0
	 */
	private function clearCacheGroups(array $clearGroups, array $cacheClients = array(0, 1))
	{
		foreach ($clearGroups as $group)
		{
			foreach ($cacheClients as $client_id)
			{
				try
				{
					$options = array(
						'defaultgroup' => $group,
						'cachebase'	=> $client_id ? JPATH_ADMINISTRATOR . '/cache' : $this->app->get('cache_path', JPATH_SITE . '/cache')
					);

					$cache = JCache::getInstance('callback', $options);
					$cache->clean();
				}
				catch (Exception $e)
				{
					// Ignore it
				}
			}
		}
	}
}
