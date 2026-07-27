<?php
/* Module 123-SMS pour Dolibarr - envoi de SMS professionnels
 * Copyright (C) 123-Sms.net - licence MIT
 * Squelette standard Dolibarr (16+). Dossier : htdocs/custom/sms123/
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modSms123 extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;
		$this->numero = 500123;
		$this->rights_class = 'sms123';
		$this->family = 'interface';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Envoi de SMS professionnels via 123-SMS.net';
		$this->descriptionlong = 'Envoi de SMS (rappels, alertes, notifications) via l\'API '
			.'HTTPS de 123-SMS.net : service francais depuis 2002, credits prepayes '
			.'sans abonnement. Page d\'envoi + classe reutilisable dans vos triggers.';
		$this->editor_name = '123-SMS.net';
		$this->editor_url = 'https://www.123-sms.net';
		$this->version = '2.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'phone';
		$this->config_page_url = array('setup.php@sms123');
		$this->module_parts = array('triggers' => 1);
		$this->depends = array();
		$this->langfiles = array('sms123@sms123');
		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(16, 0);

		// Permission : envoyer des SMS
		$this->rights = array();
		$this->rights[0][0] = 50012301;
		$this->rights[0][1] = 'Envoyer des SMS via 123-SMS';
		$this->rights[0][3] = 0;
		$this->rights[0][4] = 'envoyer';

		// Entree de menu (Outils > SMS 123-SMS)
		$this->menu = array();
		$this->menu[0] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'SMS 123-SMS',
			'mainmenu' => 'tools',
			'leftmenu' => 'sms123',
			'url' => '/sms123/sms123index.php',
			'langs' => 'sms123@sms123',
			'position' => 1000,
			'enabled' => 'isModEnabled("sms123")',
			'perms' => '$user->hasRight("sms123", "envoyer")',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Activation du module : creation de la table d'historique.
	 *
	 * @param string $options options
	 * @return int
	 */
	public function init($options = '')
	{
		$resultat = $this->_load_tables('/sms123/sql/');
		if ($resultat < 0) {
			return -1;
		}

		return $this->_init(array(), $options);
	}

	/**
	 * Desactivation du module (les donnees d'historique sont conservees).
	 *
	 * @param string $options options
	 * @return int
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
