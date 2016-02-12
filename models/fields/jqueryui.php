<?php
/***
 * @category   Plugin
 * @package    Joomla
 * @author     Original Author <serafinobilotta@hotmail.com>
 * @license    GNU/GPL, see http://www.gnu.org/licenses/gpl-2.0.html
 * @version    1.0
 * @link       http://www.p2warticles.com/2014/09/facebook-events-plugin-pro/
 * @since      File available since Release 1.0
 */
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('joomla.form.formfield');

class JFormFieldJqueryui extends JFormField
{

	protected $type = 'Jqueryui';

	// getLabel() left out

	protected function getInput()
	{
		$doc = JFactory::getDocument();
        JHtml::_('jquery.framework');
        $doc->addScript(JURI::base() . "../plugins/system/fbevents_pro/js/jquery-ui.min.js");

		$doc->addScriptDeclaration('jQuery.noConflict();
function restoreOrder() {
    var list = jQuery("#format ul");
    if (list == null) return;
    var old = jQuery("#jform_params_positions").val();
    var IDs = old.split(",");
    var items = list.sortable("toArray");
	var rebuild = new Array();
    for (var v = 0, len = items.length; v < len; v++) {
        rebuild[items[v]] = items[v];
    }
    for (var i = 0, n = IDs.length; i < n; i++) {
        var itemID = IDs[i] + "_";
        if (itemID in rebuild) {
            var item = rebuild[itemID];
            var child = jQuery("ul.ui-sortable").children("#" + item);
            var savedOrd = jQuery("ul.ui-sortable").children("#" + itemID);
            child.remove();
            jQuery("ul.ui-sortable").filter(":first").append(savedOrd);
        }
    }
}');
		$doc->addScriptDeclaration("
jQuery(function() {
    jQuery('.panel, #jform_params_positions-lbl, #jform_params_positions').hide();
    jQuery('#sortable').sortable({
        opacity: 0.6,
        update: function(event, ui) {
            aggiornaTesto();
        }
    });


    function aggiornaTesto() {
        ids = [];
        jQuery('#sortable input:checkbox').each(function(n) {
            ids.push(jQuery(this).attr('value'));
        });
        jQuery('#jform_params_positions').val(ids);
    }
    restoreOrder();

    jQuery( '#sortable [type=checkbox]' ).button();

});
        ");

		$doc->addStyleSheet("../plugins/system/fbevents_pro/css/dot-luv/jquery-ui-1.8.16.custom.css");
		$doc->addStyleDeclaration('#sortable { list-style-type: none; margin: 10px 0 25px; padding: 0; width: 150px; }
	#sortable li { margin: 8px 3px 3px 3px; padding: 0.4em; padding-left: 1.5em; height: 18px; }
	.ui-sortable .ui-button{ min-width: 150px}
	.ui-button{ min-width: 50px}
	.ui-widget{ margin:10px}
	');

        $class = !empty($this->class) ? ' class="' . $this->class . '"' : '';
        $html = '<input type="text" name="' . $this->name . '" id="' . $this->id . '" value="'
            . htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8') . '"' . $class . ' />';


		return $html;
	}
}