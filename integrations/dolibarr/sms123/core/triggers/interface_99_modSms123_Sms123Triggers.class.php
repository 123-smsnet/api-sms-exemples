<?php
/* Declencheurs SMS 123-SMS.net - licence MIT
 * Envoie un SMS sur les evenements metier de Dolibarr, sans ecrire de code :
 * tout se configure dans Configuration > Modules > SMS 123-SMS.net.
 */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceSms123Triggers extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'demo';
		$this->description = 'Envoi de SMS via 123-SMS.net sur les evenements Dolibarr';
		$this->version = '2.3.1';
		$this->picto = 'phone';
	}

	/** Evenements pris en charge : code Dolibarr => cle de traduction. */
	public static function evenements()
	{
		return array(
			'ORDER_VALIDATE' => 'Sms123EvORDER_VALIDATE',
			'BILL_VALIDATE' => 'Sms123EvBILL_VALIDATE',
			'BILL_PAYED' => 'Sms123EvBILL_PAYED',
			'SHIPPING_VALIDATE' => 'Sms123EvSHIPPING_VALIDATE',
			'PROPAL_VALIDATE' => 'Sms123EvPROPAL_VALIDATE',
		);
	}

	/**
	 * Execution du declencheur.
	 *
	 * @param string $action code de l'evenement
	 * @param object $object objet concerne
	 * @param User   $user   utilisateur
	 * @param Translate $langs traductions
	 * @param Conf   $conf   configuration
	 * @return int
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		if (!isModEnabled('sms123')) {
			return 0;
		}

		// Case « Rappel SMS » de la fiche d'un evenement d'agenda
		if (in_array($action, array('ACTION_CREATE', 'ACTION_MODIFY', 'ACTION_DELETE'), true)) {
			return $this->choixRappel($action, $object);
		}

		if (!array_key_exists($action, self::evenements())) {
			return 0;
		}
		if (!getDolGlobalString('SMS123_TRIG_'.$action)) {
			return 0; // declencheur desactive
		}

		dol_include_once('/sms123/class/sms123api.class.php');

		$modele = getDolGlobalString('SMS123_TPL_'.$action);
		if (empty($modele)) {
			return 0;
		}
		$destinataire = getDolGlobalString('SMS123_DEST_'.$action);

		if (method_exists($object, 'fetch_thirdparty')) {
			$object->fetch_thirdparty();
		}
		$socid = empty($object->thirdparty->id) ? 0 : (int) $object->thirdparty->id;

		if ($destinataire === 'admin') {
			$numero = getDolGlobalString('SMS123_NUM_ADMIN');
			$socid = 0; // alerte interne : rien a tracer sur la fiche client
		} else {
			$numero = Sms123Api::numeroTiers(empty($object->thirdparty) ? null : $object->thirdparty);
		}

		if (empty($numero)) {
			dol_syslog('Sms123 trigger '.$action.' : aucun numero de destinataire', LOG_WARNING);
			return 0;
		}

		$message = Sms123Api::appliquerModele($modele, $object);
		$code = Sms123Api::envoyer($numero, $message, 0, 'trigger '.$action, $socid);
		dol_syslog('Sms123 trigger '.$action.' -> '.$code, LOG_INFO);

		return 1;
	}

	/**
	 * Enregistre le choix « Rappel SMS » saisi sur la fiche d'un evenement.
	 *
	 * Une ligne n'est ecrite QUE si l'utilisateur s'ecarte du comportement
	 * automatique (type de l'evenement coche dans la configuration) ; s'il
	 * revient dessus, la ligne est supprimee. La fiche reste donc alignee sur
	 * la configuration tant que personne ne decide autrement.
	 *
	 * @param string $action code du declencheur
	 * @param object $object evenement d'agenda
	 * @return int
	 */
	protected function choixRappel($action, $object)
	{
		if (empty($object->id)) {
			return 0;
		}
		dol_include_once('/sms123/class/sms123cron.class.php');

		if ($action == 'ACTION_DELETE') {
			Sms123Cron::supprimerForcage($this->db, $object->id);
			return 0;
		}

		// Notre case n'etait pas dans le formulaire (evenement cree par un
		// autre module, un import, l'API...) : on ne touche a rien.
		if (!GETPOST('sms123_rappel_present', 'int')) {
			return 0;
		}

		$coche = GETPOST('sms123_rappel', 'int') ? 1 : 0;
		$auto = Sms123Cron::typeConcerne($this->db, empty($object->type_id) ? 0 : $object->type_id) ? 1 : 0;

		// A la creation, le type n'etait pas connu au moment de l'affichage :
		// la case ne sert donc qu'a forcer l'envoi, jamais a l'interdire.
		if ($action == 'ACTION_CREATE') {
			if ($coche && !$auto) {
				Sms123Cron::enregistrerForcage($this->db, $object->id, 1);
			}
			return 0;
		}

		if ($coche == $auto) {
			Sms123Cron::supprimerForcage($this->db, $object->id);
		} else {
			Sms123Cron::enregistrerForcage($this->db, $object->id, $coche);
		}

		return 0;
	}
}
