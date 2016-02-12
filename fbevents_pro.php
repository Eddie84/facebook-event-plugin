<?php

/**
 * @category   Plugin
 * @package    Joomla
 * @author     Original Author <serafinobilotta@hotmail.com>
 * @license    GNU/GPL, see http://www.gnu.org/licenses/gpl-2.0.html
 * @link       http://www.p2warticles.com/2014/09/facebook-events-plugin-pro/
 * @since      File available since Release 1.0
 */

//@TODO aggiungere impostazioni per <picture>, attending_count,declined_count,maybe_count
/**
 * ensure this file is being included by a parent file
 */
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');
jimport('joomla.html.html');

require_once( 'sdk/autoload.php' );

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;

class plgSystemFbevents_pro extends JPlugin
{
    private $parameters,
        $session = null;

	/**
	 * Constructor - note in Joomla 2.5 PHP4.x is no longer supported so we can use this.
	 *
	 * @access      protected
	 * @param       object $subject The object to observe
	 * @param       array $config An array that holds the plugin configuration
	 */
	function plgSystemFbevents_pro(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	//------------------------------------------------------------------------------------------------------------------

	function onAfterRender()
	{
		$app = JFactory::getApplication();
		if ($app->isAdmin()) {
			return;
		}

		$buffer = JFactory::getApplication()->getBody();
		if (preg_match_all('{\{fbevents(.*)\}}', $buffer, $match)) {
			//match 0 è l'intero shortcode, match 1 sono i parametri
            //match 1 sono i parametri passati

            foreach ($match[1] as $key => $founded) {
                $this->parameters = $this->makeParams($founded);
                if ($this->parameters->id == null) {
                    //multiple events
                    $htmlToInject = $this->getFbEvents();
                } else {
                    //a single event given an id in regex
                    $htmlToInject = $this->getFbEvent();
                }
                $buffer = str_replace($match[0], $htmlToInject, $buffer);
            }

			$fbScriptTag = "<div id=\"fb-root\"></div>";


			$buffer = str_replace('</body>', $fbScriptTag . "</body>", $buffer);
            JFactory::getApplication()->setBody($buffer);
		}
	}

	//------------------------------------------------------------------------------------------------------------------

	function onAfterDispatch()
	{
		$app = JFactory::getApplication();
		if ($app->isAdmin()) {
			return;
		}
        $appId = $this->params->get('appId');

		$document = JFactory::getDocument();
		$document->addScript("//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js");
		$document->addScript(JURI::base() . "plugins/system/fbevents_pro/js/sb-rsvp.js");
		$document->addScriptDeclaration("
            fbEventsObject = {
                appId: $appId,
                baseUrl: '" . JURI::base() . "'
            };
        ");

	}

	//------------------------------------------------------------------------------------------------------------------

	/**
	 * Crea la query per gli eventi di un @creator e restituisce l'html
	 *
	 * @return string
	 */
    function getFbEvents()
    {

		//get date for facebook query
        $now = substr(date("c"), 0, -5);
        $photo = $this->parameters->pic == 'cover' ? $this->parameters->pic : "picture.type({$this->parameters->pic})";

        $query = new stdClass();
        $query->source = $this->parameters->idSource . "/events";
        $query->params = array(
            'limit' => $this->parameters->limitEvents,
            'fields' => "name,$photo,id,start_time,end_time,venue,location,description,updated_time,ticket_uri"
        );

        if ($this->parameters->showEvents == "all") {
            $query->params['since'] = 0;
        } elseif ($this->parameters->showEvents == "new") {
            $query->params['since'] = $now;
        } else {
            $query->params['since'] = 0;
            $query->params['until'] = $now;
        }

        $result = $this->facebookCall($query);

        //sort array
        $result->data = $this->quickSortResults($result->data);

        //Start making html
        $html = "<div id=\"sb-fb-events\" data-limit=\"{$this->parameters->limitUser}\" data-status=\"{$this->parameters->attendantStatus}\">";

        //get all values
        foreach ($result->data as $keys => $event) {
            $html .= $this->getSingleEvent($event);
        }

        $html .= "</div>";
        return $html;
    }

	//------------------------------------------------------------------------------------------------------------------/**

	/** Crea la query per gli eventi dato un id e restituisce l'html
     *
	 * @return string
	 */
	function getFbEvent()
	{
        //get date for facebook query
        $photo = $this->parameters->pic == 'cover' ? $this->parameters->pic : "picture.type({$this->parameters->pic})";

        $query = new stdClass();
        $query->source = $this->parameters->id;
        $query->params = array(
            'fields' => "name,$photo,id,start_time,end_time,venue,location,description,updated_time,ticket_uri"
        );

        $result = $this->facebookCall($query);

		//Start making html
		$html = "<div id=\"sb-fb-events\" data-limit=\"{$this->parameters->limitUser}\" data-status=\"{$this->parameters->attendantStatus}\">";
		$html .= $this->getSingleEvent($result);
		$html .= "</div>";

		return $html;
	}

	//------------------------------------------------------------------------------------------------------------------

	/**
	 * Funzione che fa vedere un solo evento specifico
	 *
	 * @method getEvent
	 * @param mixed $event dell'evento
	 * @return string
	 */
	function getSingleEvent($event)
	{
		$eventHtml = "";
        setlocale(LC_TIME, $this->parameters->dateLang);

        $id = $event->id;
        $eventUrl = "http://www.facebook.com/events/".$id;

        $startEvent = $this->dateFBPreciseToDateInt($event->start_time);
        $endEvent = ($event->end_time != '') ? $this->dateFBPreciseToDateInt($event->end_time) : NULL;

        $startTime = strftime($this->parameters->timeFormat, $startEvent);
        $endTime = ($endEvent != NULL) ? strftime($this->parameters->timeFormat, $endEvent) : '00:00';

        $startDay = strftime('%Y-%m-%d', $startEvent);
        $endDay = ($endEvent != NULL) ? strftime('%Y-%m-%d', $endEvent) : '0000-00-00';

        $eventHtml .= "<div id=\"$id\" class=\"event\" itemscope itemtype=\"http://schema.org/Event\">";

        $microData = new stdClass();

        if ($this->parameters->enableMicrodata == 'all' ||
            ($this->parameters->enableMicrodata == 'new' && $event->start_time >= date(DateTime::ISO8601)) ) {

            $microData->name = "itemprop=\"name\"";
            $microData->description = "itemprop=\"description\"";
            $microData->sameAs = "itemprop=\"sameAs\"";
            $microData->url = "itemprop=\"url\"";
            $microData->StartDate = "<span itemprop=\"startDate\" content=\"{$event->start_time}\">";
            $microData->EndDate = $event->end_time != NULL ? "<span itemprop=\"endDate\" content=\"{$event->end_time}\">" : "<span>";
            $microData->CloseDateTag = "</span>";
            $microData->img = "itemprop=\"image\"";
            $microData->location = "itemprop=\"location\" itemscope itemtype=\"http://schema.org/Place\"";
            $microData->postalAddress = "itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\"";
            $microData->streetAddress = "itemprop=\"streetAddress\"";
            $microData->addressLocality = "itemprop=\"addressLocality\"";
            $microData->postalCode = "itemprop=\"postalCode\"";
            $microData->addressRegion = "itemprop=\"addressRegion\"";
            $microData->geo = "itemprop=\"geo\" itemscope itemtype=\"http://schema.org/GeoCoordinates\"";
            $microData->geoLatitude = "itemprop=\"latitude\"";
            $microData->geoLongitude = "itemprop=\"longitude\"";
            $microData->ticket = "itemprop=\"offers\" itemscope itemtype=\"http://schema.org/Offer\"";

        }

        //Same day or no end_time
        if ($startDay == $endDay || $endEvent == NULL) {
            //day
            $date = "<p class=\"{$this->parameters->dateClass}\">";
            $date .= $microData->StartDate;
            $date .= utf8_encode(ucwords(strftime($this->parameters->dateFormat, $startEvent))) . " ";
            $date .= $microData->CloseDateTag;
            $date .= ($startTime != '00:00') ? ($this->parameters->timeSeparator . $startTime) : "";
            $date .= $microData->EndDate;
            $date .= ($endTime != '00:00') ? ($this->parameters->toText . " " . $endTime) : " ";
            $date .= $microData->CloseDateTag;
            $date .= "</p>";
        } else {
            //days & times
            $date = "<p class=\"{$this->parameters->dateClass}\">";
            $date .= $microData->StartDate;
            $date .= utf8_encode(ucwords(strftime($this->parameters->fullDateFormat, $startEvent)));
            $date .= $microData->CloseDateTag;
            $date .= " {$this->parameters->toText} ";
            $date .= $microData->EndDate;
            $date .= utf8_encode(ucwords(strftime($this->parameters->fullDateFormat, $endEvent)));
            $date .= $microData->CloseDateTag;
            $date .= "</p>";
        }
        if ($this->parameters->linkedTitle) {
            $title = "<{$this->parameters->titleTag} class=\"{$this->parameters->titleClass}\" {$microData->name}>";
            $title .= "<a href=\"{$eventUrl}\" target=\"_blank\" {$microData->sameAs}>{$event->name}</a>";
            $title .= "</{$this->parameters->titleTag}>";
        } else {
            $title = "<{$this->parameters->titleTag} class=\"{$this->parameters->titleClass}\" {$microData->name}>{$event->name}</{$this->parameters->titleTag}>";
        }

        foreach ($this->parameters->showFields as $field) {
            switch ($field) {
                case "image" :
                    if ($this->parameters->pic == 'cover') {
                        $picCover = $event->{$this->parameters->pic};
                        if ($this->parameters->coverSetting == 'full') {
                            $eventHtml .= "<img src='" . $picCover->source
                                . "' class=\"{$this->parameters->picClass}\" {$microData->img} />";
                        } elseif ($this->parameters->coverSetting == 'cover') {
                            $eventHtml .= "<div class=\"fbCover\" style=\"overflow:hidden; width: 100%; position:relative \">";
                            $eventHtml .= " <img src=\"{$picCover->source}\"  data-offset-y=\"{$picCover->offset_y}\" style=\"position:absolute; width:100%\" {$microData->img} />";
                            $eventHtml .= "</div>";
                        }

                    } else {
                        $eventHtml .= "<img src='" . $event->picture->data->url
                            . "' class=\"{$this->parameters->picClass}\" {$microData->img} />";
                    }

                    break;
                case "title" :
                    $eventHtml .= $title;
                    break;
                case "date" :
                    $eventHtml .= $date;
                    break;
                case "update_time" :
                    $eventHtml .= "<div class=\"{$this->parameters->updateTimeClass}\">{$this->parameters->lastUpdateText}" . utf8_encode(ucwords(strftime($this->parameters->dateFormatUpdate, $event->update_time))) . "</div>";
                    break;
                case "description" :
                    if ($this->parameters->descriptionLimit) {
                        $desc = substr($event->description, 0, trim($this->parameters->descriptionLimit));
                    } else {
                        $desc = $event->description;
                    }
                    $desc = nl2br($desc);
                    $eventHtml .= "<div class=\"{$this->parameters->descriptionClass}\" {$microData->description}>$desc</div>";
                    break;
                case "venue" :
                    $eventHtml .= "<div class=\"{$this->parameters->locationClass}\" {$microData->location}>";
                    if ($event->venue->id != "") {
                        $eventHtml .= "{$this->parameters->locationText} <a href=\"http://www.facebook.com/{$event->venue->id}\" target=\"_blank\" {$microData->url} {$microData->name}>";
                        $eventHtml .= $event->location;
                        $eventHtml .= "</a>";
                        $eventHtml .= "<div {$microData->postalAddress}>";
                        $eventHtml .= "    <span {$microData->streetAddress} class='street'>{$event->venue->street}</span>";
                        $eventHtml .= "    <span {$microData->addressLocality} class='city'>{$event->venue->city}</span>";
                        $eventHtml .= "    <meta {$microData->postalCode} content=\"{$event->venue->zip}\" />";
                        $eventHtml .= "    <meta {$microData->addressRegion} content=\"{$event->venue->country}\" />";
                        $eventHtml .= "</div>";
                        $eventHtml .= "<div {$microData->geo} class='hidden'>";
                        $eventHtml .= "    <meta {$microData->geoLatitude} content=\"{$event->venue->latitude}\" />";
                        $eventHtml .= "    <meta {$microData->geoLongitude} content=\"{$event->venue->longitude}\" />";
                        $eventHtml .= "</div>";
                    } else {
                        $eventHtml .= "{$this->parameters->locationText} <span {$microData->name}>{$event->location}</span>";
                    }
                    $eventHtml .= "</div>";
                    break;
                case "link" :
                    $eventHtml .= "<div class=\"{$this->parameters->linkClass}\">{$this->parameters->linkText}";
                    $eventHtml .= "    <a target=\"_blank\" {$microData->sameAs} href=\"{$eventUrl}\">http://www.facebook.com/events/$id</a>";
                    $eventHtml .= "</div>";
                    break;
                case "rsvp" :
                    $buttons = "\n<div class=\"buttons_event {$this->parameters->rsvpClass}\" data-id=\"$id\">\n";
                    if ($this->parameters->btnAttend) {
                        $buttons .= "\t<button class=\"btn {$this->parameters->btnClassAttend}\" data-event=\"$id\" data-get=\"attending\" data-post=\"attending\">{$this->parameters->btnTextAttend}</button>\n";
                    }
                    if ($this->parameters->btnUnsure) {
                        $buttons .= "\t<button class=\"btn {$this->parameters->btnClassUnsure}\" data-event=\"$id\" data-get=\"unsure\" data-post=\"maybe\">{$this->parameters->btnTextUnsure}</button>\n";
                    }
                    if ($this->parameters->btnDeclined) {
                        $buttons .= "\t<button class=\"btn {$this->parameters->btnClassDeclined}\" data-event=\"$id\" data-get=\"declined\" data-post=\"declined\">{$this->parameters->btnTextDeclined}</button>\n";
                    }
                    $buttons .= "</div>\n";
                    $eventHtml .= $buttons;
                    break;
                case "attendants" :
                    $eventHtml .= "<div class=\"attendants\"><img src=\"{$this->parameters->loadingImg}\" /></div>";
                    break;
                case "ticket_uri" :
                    $eventHtml .= "<div class=\"{$this->parameters->ticketDivClass}\" {$microData->ticket}>";
                    $eventHtml .= "    <a {$microData->url} href=\"{$event->ticket_uri}\" target=\"_blank\" class=\"{$this->parameters->ticketLinkClass}\">{$this->parameters->ticketText}</a>";
                    $eventHtml .= "</div>";
                    break;

            }
        }
        $eventHtml .= "<div style='clear: both'></div>";
        $eventHtml .= "</div>";

        return $eventHtml;
	}

	//------------------------------------------------------------------------------------------------------------------

	/**
	 * @param string $dateIn
	 * @return bool|int
	 */
	function dateFBPreciseToDateInt($dateIn = '')
	{
		if ($dateIn == '')
			return false;

		$year = substr($dateIn, 0, 4);
		$month = substr($dateIn, 5, 2);
		$day = substr($dateIn, 8, 2);
		$hour = substr($dateIn, 11, 2);
		$minute = substr($dateIn, 14, 2);
		$second = substr($dateIn, 17, 2);

		return mktime($hour, $minute, $second, $month, $day, $year);

	}

	//------------------------------------------------------------------------------------------------------------------

	/**
	 * Parserizza una stringa e restituisce un array con gli attributi e i relativi valori
	 *
	 * @param string $regexResult risultato della regex nel formato param1=value1|param2=value2|ecc
	 * @return array $result[paramName] array di parametri
	 */
	function parseVar($regexResult)
	{
		$result = array();
		$firstPass = explode('|', $regexResult);
		foreach ($firstPass as $p) {
			$secondPass = explode('=', trim($p));
			$result[trim($secondPass[0])] = trim($secondPass[1]);
		}
		return $result;
	}

	//------------------------------------------------------------------------------------------------------------------

	/**
	 * Prende il valore di un parametro dalla regex se presente nello shortcode
	 *
	 * @param string $name nome parametro
	 * @param mixed $regexParams parametri presenti nello shortcode
	 * @return mixed
	 */
	function getParam($name, $regexParams = "")
	{
		if ($regexParams[$name]) {
            if (count($explodedValue = explode(',', $regexParams[$name])) > 1)
                return $explodedValue;
            else
                return $regexParams[$name];
		} else {
			return $this->params->get($name);
		}
	}

    //------------------------------------------------------------------------------------------------------------------

    /**
     * Crea i parametri di configurazione
     *
     * @param mixed $regex parametri presenti nello shortcode
     * @return mixed
     */
    function makeParams($regex = '') {
        $regParams = array();
        if ($regex) {
            $regParams = $this->parseVar($regex);
        }
        $params = new stdClass();

        //Params for Fb General Settings tab
        $params->appId = $this->params->get('appId');
        $params->secretKey = $this->params->get('secret');
        $params->idSource = $this->getParam('fbUserName', $regParams);
        $params->showEvents = $this->getParam('showEvents', $regParams); //new, past, all
        $params->limitEvents = $this->getParam('limitEvents', $regParams);
        $params->orderEvents = $this->getParam('orderEvents', $regParams); // ASC or DESC
        $params->enableMicrodata = $this->getParam('enableMicrodata', $regParams);

        //Params for Fields tab
        $params->showFields = (array)$this->getParam('showFields', $regParams);

        //Params for Title Settings tab
        $params->linkedTitle = $this->getParam('linkedTitle', $regParams);
        $params->titleTag = $this->getParam('titleTag', $regParams);
        $params->titleClass = $this->getParam('titleClass', $regParams);

        //Params for Picture Settings tab
        $params->pic = $this->getParam('pic', $regParams);
        $params->picClass = $this->getParam('picClass', $regParams);
        $params->coverSetting = $this->getParam('coverSetting', $regParams);

        //Params for Date Settings tab
        $params->dateLang = $this->getParam('dateLang', $regParams); // for week days names
        $params->timeSeparator = $this->getParam('timeSeparator', $regParams);
        $params->toText = $this->getParam('toText', $regParams);
        $params->lastUpdateText = $this->getParam('lastUpdateText', $regParams);
        $params->dateClass = $this->getParam('dateClass', $regParams); // a class for the div with date
        $params->updateTimeClass = $this->getParam('updateTimeClass', $regParams);
        $params->fullDateFormat = $this->getParam('fullDateFormat', $regParams);
        $params->dateFormat = $this->getParam('dateFormat', $regParams);
        $params->timeFormat = $this->getParam('timeFormat', $regParams);
        $params->dateFormatUpdate = $this->getParam('dateFormatUpdate', $regParams);

        //Params for Description tab
        $params->descriptionClass = $this->getParam('descriptionClass', $regParams);
        $params->descriptionLimit = $this->getParam('descriptionLimit', $regParams);

        //Params for TicketUrl tab
        $params->ticketDivClass = $this->getParam('ticketDivClass', $regParams);
        $params->ticketLinkClass = $this->getParam('ticketLinkClass', $regParams);
        $params->ticketText = $this->getParam('ticketText', $regParams);

        //Params for Location Tab
        $params->locationText = $this->getParam('locationText', $regParams);
        $params->locationClass = $this->getParam('locationClass', $regParams);

        //Params for RSVP Buttons tab
        $params->rsvpClass = $this->getParam('rsvpClass', $regParams);
        $params->btnAttend = $this->getParam('btnAttend', $regParams);
        $params->btnUnsure = $this->getParam('btnUnsure', $regParams);
        $params->btnDeclined = $this->getParam('btnDeclined', $regParams);
        $params->btnTextAttend = $this->getParam('btnTextAttend', $regParams);
        $params->btnTextUnsure = $this->getParam('btnTextUnsure', $regParams);
        $params->btnTextDeclined = $this->getParam('btnTextDeclined', $regParams);
        $params->btnClassAttend = $this->getParam('btnClassAttend', $regParams);
        $params->btnClassUnsure = $this->getParam('btnClassUnsure', $regParams);
        $params->btnClassDeclined = $this->getParam('btnClassDeclined', $regParams);

        //Params for Link tab
        $params->linkText = $this->getParam('linkText', $regParams);
        $params->linkClass = $this->getParam('linkClass', $regParams);

        //Params for UserList tab
        $params->loadingImg = $this->getParam('loadingImg', $regParams);
        $params->limitUser = $this->getParam('limitUser', $regParams);
        $params->linkedUser = $this->getParam('linkedUser', $regParams);
        $params->attendantStatus = $this->getParam('attendantStatus', $regParams);

        //Single event with id
        $params->id = $this->getParam('id', $regParams);

        return $params;

    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * Chiamata a Facebook per avere gli eventi
     *
     * @param stdClass $query openGraph
     * @return stdClass $result
     */
    function facebookCall($query) {

        // FACEBOOK SDK

        FacebookSession::setDefaultApplication($this->parameters->appId, $this->parameters->secretKey);

        // If you're making app-level requests:
        if ($this->session == null) {
            try {
                $this->session = FacebookSession::newAppSession();
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }

            // To validate the session:
            try {
                $this->session->validate();
            } catch (FacebookRequestException $ex) {
                // Session not valid, Graph API returned an exception with the reason.
                echo $ex->getMessage();
            } catch (\Exception $ex) {
                // Graph API returned info, but it may mismatch the current app or have expired.
                echo $ex->getMessage();
            }
        }


        $request = new FacebookRequest( $this->session, 'GET', "/".$query->source, $query->params );
        $response = $request->execute();
        // get response
        $fbResult = $response->getResponse();

        return $fbResult;

    }

    //------------------------------------------------------------------------------------------------------------------

    /**
     * Funzione per riordinare gli eventi in base alla data
     *
     * @param string $array
     * @return stdClass $result
     */
    function quickSortResults( $array ) {

        usort($array, function($a, $b)
        {
            if (strtotime($a->start_time) == strtotime($b->start_time)) {
                return 0;
            }
            return (strtotime($a->start_time) > strtotime($b->start_time)) ? -1 : 1;
        });

        //order ASC or DESC
        if ($this->parameters->orderEvents == 'asc') {
            krsort($array);
        }

        return $array;
    }
}
