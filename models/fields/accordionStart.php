<?php
/**
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

class JFormFieldAccordionstart extends JFormField
{

	protected $type = 'Accordionstart';

	public function getLabel()
	{
		return '';
	}

	public function getInput()
	{
		return '
    </div>
</div>
<div class="span6">
    <h3>
        <a class="btn-block btn-large btn" data-target="#' . $this->element['data-id'] . '" data-toggle="collapse">
            ' . $this->element['label'] . ' <span class="caret"></span>
        </a>
    </h3>
    <div id="' . $this->element['data-id'] . '" class="collapse">
    <div class="hidden">
        <div>';
	}
}