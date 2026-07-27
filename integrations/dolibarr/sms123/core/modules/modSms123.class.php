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
			.'sans abonnement. Page d\'envoi, envoi en masse, declencheurs, rappels de '
			.'rendez-vous, relances de factures, accuses de reception et classe '
			.'reutilisable dans vos propres scripts.';
		$this->editor_name = '123-SMS.net';
		$this->editor_url = 'https://www.123-sms.net';
		$this->version = '2.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'phone';
		$this->config_page_url = array('setup.php@sms123');
		$this->module_parts = array(
			'triggers' => 1,
			'hooks' => array(
				'thirdpartycard', 'contactcard', 'propalcard', 'ordercard',
				'invoicecard', 'expeditioncard',
				'thirdpartylist', 'contactlist',
			),
		);

		// Widget disponible sur l'accueil (Accueil > Configuration > Widgets)
		$this->boxes = array(
			0 => array(
				'file' => 'box_sms123.php@sms123',
				'note' => 'Solde 123-SMS.net et derniers SMS envoyes',
				'enabledbydefault' => 0,
			),
		);

		// Taches planifiees (a activer dans Accueil > Configuration > Taches planifiees)
		$this->cronjobs = array(
			0 => array(
				'label' => 'Rappels de rendez-vous par SMS',
				'jobtype' => 'method',
				'class' => '/sms123/class/sms123cron.class.php',
				'objectname' => 'Sms123Cron',
				'method' => 'rappelsRendezVous',
				'parameters' => '',
				'comment' => 'Envoie un SMS avant les evenements d agenda des types choisis dans la configuration du module 123-SMS.',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => 'isModEnabled("sms123")',
				'priority' => 50,
			),
			1 => array(
				'label' => 'Relances de factures impayees par SMS',
				'jobtype' => 'method',
				'class' => '/sms123/class/sms123cron.class.php',
				'objectname' => 'Sms123Cron',
				'method' => 'relancesFactures',
				'parameters' => '',
				'comment' => 'Envoie un SMS de relance aux clients dont la facture est echue, selon les reglages du module 123-SMS.',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("sms123")',
				'priority' => 51,
			),
			2 => array(
				'label' => 'Alerte de solde bas 123-SMS',
				'jobtype' => 'method',
				'class' => '/sms123/class/sms123cron.class.php',
				'objectname' => 'Sms123Cron',
				'method' => 'alerteSolde',
				'parameters' => '',
				'comment' => 'Previent par e-mail (et par SMS) lorsque le credit du compte 123-SMS passe sous le seuil configure.',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("sms123")',
				'priority' => 52,
			),
		);
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

		// Entrees de menu (Outils > SMS 123-SMS)
		$this->menu = array();
		$this->menu[0] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'Sms123Menu',
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
		$this->menu[1] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sms123',
			'type' => 'left',
			'titre' => 'Sms123MenuMass',
			'mainmenu' => 'tools',
			'leftmenu' => 'sms123masse',
			'url' => '/sms123/sms123masse.php',
			'langs' => 'sms123@sms123',
			'position' => 1001,
			'enabled' => 'isModEnabled("sms123")',
			'perms' => '$user->hasRight("sms123", "envoyer")',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Activation du module : creation puis mise a jour de la table d'historique.
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
		$this->migrer();

		return $this->_init(array(), $options);
	}

	/**
	 * Ajoute les colonnes apparues apres la version 2.1 sur une base deja
	 * installee (fk_soc, reference, statut, date_ar, erreur_ar).
	 *
	 * La presence d'une colonne est testee par un SELECT qui ne ramene aucune
	 * ligne : s'il aboutit, la colonne existe et rien n'est fait ; sinon elle
	 * est ajoutee. On n'utilise ici que query() et free(), presentes sur tous
	 * les pilotes de base de Dolibarr : la methode ne peut pas interrompre
	 * l'activation du module.
	 *
	 * @return int 1
	 */
	public function migrer()
	{
		$table = MAIN_DB_PREFIX.'sms123_envoi';
		$colonnes = array(
			'fk_soc' => 'integer NULL',
			'reference' => 'varchar(64) NULL',
			'statut' => 'varchar(16) NULL',
			'date_ar' => 'datetime NULL',
			'erreur_ar' => 'varchar(64) NULL',
		);

		foreach ($colonnes as $nom => $type) {
			$test = $this->db->query('SELECT '.$nom.' FROM '.$table.' WHERE 1 = 0');
			if ($test) {
				$this->db->free($test);
				continue;
			}
			$this->db->query('ALTER TABLE '.$table.' ADD COLUMN '.$nom.' '.$type);
		}

		return 1;
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
