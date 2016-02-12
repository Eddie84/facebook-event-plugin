var $fbPluginSelector;
/**
 * Funzione che aggiorna l'accoppiata di bottoni relativi all'evento
 * @method updateButtons
 * @param {string} status stato della risposta
 * @param {int} id evento
 * @return {void}
 */
function updateButtons(status, id) {
    $fbPluginSelector.find('button').each(function () {
        var $that = jQuery(this);
        if ($that.data('event') == id) {
            $that.removeClass('disable').prop('disabled', false);
            if ($that.data('get') === status) {
                $that.addClass('disable').prop('disabled', true);
            }
        }

    });
}
//----------------------------------------------------------------------------------------------------------------------

/**
 * Funzione che all'inizio aggiorna lo stato di tutti i bottoni della pagina
 * @method updateAllButtons
 * @return {void}
 */
function updateAllButtons(response) {
    var uid = response.authResponse.userID;
    $fbPluginSelector.find('.buttons_event').each(function (i) {
        var id = jQuery(this).data('id');
        var query_load = '/' + id + '/invited/' + uid;
        FB.api(query_load, function (rsvp) {
            if (rsvp.data[0] !== undefined) {
                updateButtons(rsvp.data[0].rsvp_status, id);
            }
        });
    });
}
//----------------------------------------------------------------------------------------------------------------------

/**
 * Funzione che fa il login a Facebook e dopo il click cambia la stato dell'utente sull'evento di Facebook
 * @method rsvpToFbEvent
 * @return {void}
 */
function rsvpToFbEvent() {
    $fbPluginSelector.find('button:enabled')
        .on('click', function () {

            var $that = jQuery(this),
                id = $that.data('event'),
                action = $that.data('post'),
                status = $that.data('get');

            FB.getLoginStatus(function (response) {

                if (response.status === 'connected') {
                    FB.api('/' + id + '/' + action, 'post', function (response) {
                        //console.log(response)
                        if (!response || response.error) {
                            alert('Error occured');
                        } else {
                            updateButtons(status, id);
                        }
                    });
                } else {
                    FB.login(function (authorized) {
                        //var accessToken = response.authResponse.accessToken;
                        if (authorized.authResponse) {
                            FB.api('/' + id + '/' + action, 'post', function (response) {
                                if (!response || response.error) {
                                    alert('Error occured');
                                } else {
                                    updateButtons(status, id);
                                    updateAllButtons(authorized);
                                }
                            })
                        } else {
                            alert('Error: ' + authorized.error.message);
                        }
                    }, {
                        scope: 'rsvp_event'
                    });

                }
            })
        })
}
//----------------------------------------------------------------------------------------------------------------------

/**
 * Funzione che fa vedere tutti gli utenti invitati o che partecipano all'evento
 * @method getAllAttendants
 * @param {string} baseUrl indirizzo della richiesta json
 * @return {void}
 */

function getAllAttendants(baseUrl) {

    var $events = $fbPluginSelector.find('.event'),
        requestUrl = baseUrl + 'plugins/system/fbevents_pro/request_attendant.php',
        limitUser = $fbPluginSelector.data('limit'),
        attendantStatus = $fbPluginSelector.data('status'),
        requestParams = {
            'limit': limitUser,
            'status': attendantStatus
        },
        eventIds = '';

    $events.each(function (i) {
        eventIds += jQuery(this).attr('id');
        if ($events.length > 1 && i != $events.length - 1)
            eventIds += ',';
    });

    requestParams.id = eventIds;

    //chiamata ajax
    jQuery.post(requestUrl, requestParams, function (data) {

        jQuery.each(data, function (i, item) {
            var $divAttendant = jQuery('#' + i).find('.attendants');
            $divAttendant.empty();

            jQuery.each(item.data, function () {
                var name = this.name,
                    picture = this.picture.data.url,
                    profileUrl = jQuery('<a>').attr('href', 'http://facebook.com/profile.php?id=' + this.id)
                        .attr('title', name).attr('target', '_blank');
                jQuery("<img>").attr("src", picture).attr("alt", name).appendTo(profileUrl);
                jQuery(profileUrl).appendTo($divAttendant);
            });


        });

    });
}
//----------------------------------------------------------------------------------------------------------------------

/**
 * Funzione che fa il crop delle cover di Facebook
 * @method computeFbCoverCrop
 * @param {string} $coverContainer contenitore della cover
 * @return {void}
 */
function calcCoverValue($coverContainer) {

    var contW = $coverContainer.width(),
        imgHeight,
        offsetY,
        $image = $coverContainer.find('img');

    imgHeight = $image.height();
    offsetY = $image.attr('data-offset-y');
    $coverContainer.height(parseInt(contW * 0.3763));
    $image.css({
        top: parseInt(((imgHeight - $coverContainer.height()) * (offsetY * 0.01)) * -1) - 10
    });

}
//----------------------------------------------------------------------------------------------------------------------

/**
 * Main
 * @method document.ready
 */
jQuery.noConflict();
jQuery(document).ready(function () {

    $fbPluginSelector = jQuery('#sb-fb-events');

    jQuery.ajaxSetup({cache: true});
    jQuery.getScript('//connect.facebook.net/en_US/sdk.js', function () {
        window.fbAsyncInit = function () {
            FB.init({
                appId: fbEventsObject.appId,
                xfbml: false,
                version: 'v2.2'
            });
            FB.getLoginStatus(function (response) {
                if (response.status === 'connected') {
                    updateAllButtons(response);
                }
            });
        };

        var $fbCover = $fbPluginSelector.find('.fbCover');

        if ($fbCover.length) {
            $fbCover.each(function () {
                calcCoverValue(jQuery(this));
            });
            jQuery(window).on('resize', function () {
                $fbCover.each(function () {
                    calcCoverValue(jQuery(this));
                });
            });
        }
    });

    rsvpToFbEvent();
    getAllAttendants(fbEventsObject.baseUrl);

});
