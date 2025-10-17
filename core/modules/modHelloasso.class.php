<?php

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module HelloAsso
 */
class modHelloasso extends DolibarrModules
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs;

        $this->db = $db;

        // Unique module number (must not conflict with others)
        $this->numero = 26400;

        $this->rights_class = 'helloasso';
        $this->family = 'other';
        $this->module_position = '90';
        $this->name = 'HelloAsso';
        $this->description = 'Module permettant de gérer les webhooks HelloAsso.';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_HELLOASSO';
        $this->picto = 'generic';

        // Data directories to create when module enabled
        $this->dirs = array('helloasso/temp');

        // Define module pages (if needed)
        $this->config_page_url = array('helloasso.php@helloasso');

        // Hooks: none specific (we expose a webhook endpoint instead)
        $this->module_parts = array(
        	'triggers' => 0,
        );

        // Constants set at module enable
        $this->const = array(
			0 => array(
				'MAIN_MODULE_HELLOASSO',
				'yes',
				'Enable HelloAsso module',
				0
			),
        );

        // No permissions or menus at this stage
        $this->rights = array();
        $this->menus = array();
    }
}
