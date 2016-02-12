<?php

/***
 * @author     Original Author <serafinobilotta@hotmail.com>
 * @license    GNU/GPL, see http://www.gnu.org/licenses/gpl-2.0.html
 * @link       http://www.p2warticles.com/2014/09/facebook-events-plugin-pro/
 * @since      File available since Release 1.0
 * @description File con richieste ajax per mostrare gli utenti partecipanti o invitati a un evento
 */

//@todo fare upgrade per joomla 3.x
header('Content-Type: application/json; charset=UTF-8');

define('_JEXEC', 1);
define('JPATH_BASE', realpath(dirname(__FILE__) . '/../../..'));

// Load system defines
if (file_exists(JPATH_BASE . '/defines.php'))
{
    require_once JPATH_BASE . '/defines.php';
}
if (!defined('_JDEFINES'))
{
    require_once JPATH_BASE . '/includes/defines.php';
}

// Get the framework.
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

// Facebook Object
require JPATH_BASE . '/plugins/system/fbevents_pro/sdk/autoload.php';
use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;

$mainframe =& JFactory::getApplication('site');
$session =& JFactory::getSession();

// Get POST attributes
$jinput = JFactory::getApplication()->input;
$id = $jinput->get('id', '', SAFE_HTML);
$status = $jinput->get('status', 'attending', SAFE_HTML);
$limit = $jinput->get('limit', '5', SAFE_HTML);

// Get Plugin parameters
$plugin = JPluginHelper::getPlugin('system', 'fbevents_pro');
$params = new JRegistry();
$params->loadString($plugin->params);

$session = null;

//session_start();
if (!class_exists('FacebookSession')) {

    FacebookSession::setDefaultApplication($params->get('appId'), $params->get('secret'));

    // If you're making app-level requests:
    try {
        $session = FacebookSession::newAppSession();
    } catch (Exception $ex) {
        echo $ex->getMessage();
    }


// To validate the session:
    try {
        $session->validate();
    } catch (FacebookRequestException $ex) {
        // Session not valid, Graph API returned an exception with the reason.
        echo $ex->getMessage();
    } catch (Exception $ex) {
        // Graph API returned info, but it may mismatch the current app or have expired.
        echo $ex->getMessage();
    }

    $request = new FacebookRequest( $session, 'GET', "/$status?ids=$id&fields=name,picture.type(square)&limit=$limit" );
    $response = $request->execute();
    // get response
    $graphResult = $response->getRawResponse();

    echo $graphResult;
}

