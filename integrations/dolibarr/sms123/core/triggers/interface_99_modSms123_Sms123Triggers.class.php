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
		$this->version = '2.0.0';
		$this->picto = 'phone';
	}

	/** Evenements pris en charge : code Dolibarr => libelle affiche. */
	public static function evenements()
	{
		return array(
			'ORDER_VALIDATE' => 'Commande valid&eacute;e',
			'BILL_VALIDATE' => 'Facture valid&eacute;e',
			'BILL_PAYED' => 'Facture pay&eacute;e',
			'SHIPPING_VALIDATE' => 'Exp&eacute;dition valid&eacute;e',
			'PROPAL_VALIDATE' => 'Devis valid&eacute;',
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

		if ($destinataire === 'admin') {
			$numero = getDolGlobalString('SMS123_NUM_ADMIN');
		} else {
			if (method_exists($object, 'fetch_thirdparty')) {
				$object->fetch_thirdparty();
			}
			$numero = Sms123Api::numeroTiers(empty($object->thirdparty) ? null : $object->thirdparty);
		}

		if (empty($numero)) {
			dol_syslog('Sms123 trigger '.$action.' : aucun numero de destinataire', LOG_WARNING);
			return 0;
		}

		$message = Sms123Api::appliquerModele($modele, $object);
		$code = Sms123Api::envoyer($numero, $message, 0, 'trigger '.$action);
		dol_syslog('Sms123 trigger '.$action.' -> '.$code, LOG_INFO);

		return 1;
	}
}
