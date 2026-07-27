<?php
/* Taches planifiees du module 123-SMS - licence MIT
 * - rappelsRendezVous() : SMS de rappel avant les evenements d'agenda
 * - relancesFactures()  : SMS de relance sur les factures impayees
 * - alerteSolde()       : alerte e-mail/SMS quand le credit devient bas
 * Les trois sont declarees dans Accueil > Configuration > Taches planifiees.
 */

class Sms123Cron
{
	public $output = '';
	public $error = '';

	/**
	 * Rappels de rendez-vous : SMS X heures avant les evenements d'agenda
	 * dont le type est coche dans la configuration du module.
	 * A planifier toutes les heures.
	 *
	 * @return int 0 si OK
	 */
	public function rappelsRendezVous()
	{
		global $db;

		$this->output = '';
		$this->error = '';
		dol_include_once('/sms123/class/sms123api.class.php');

		if (!getDolGlobalString('SMS123_RDV_ACTIF')) {
			$this->output = Sms123Api::t('Sms123CronRdvOff');
			return 0;
		}

		$listeTypes = array();
		foreach (explode(',', getDolGlobalString('SMS123_RDV_TYPES')) as $t) {
			$t = (int) trim($t);
			if ($t > 0) {
				$listeTypes[] = $t;
			}
		}
		if (!count($listeTypes)) {
			$this->output = Sms123Api::t('Sms123CronRdvNoType');
			return 0;
		}

		$heures = (int) getDolGlobalString('SMS123_RDV_HEURES');
		if ($heures <= 0) {
			$heures = 24;
		}
		$modele = getDolGlobalString('SMS123_RDV_TPL');
		if (empty($modele)) {
			$modele = 'Rappel : rendez-vous le {date} a {heure}. {masociete}';
		}

		// Fenetre d une heure autour de l echeance (le cron tourne chaque heure)
		$debut = dol_now() + ($heures * 3600);
		$fin = $debut + 3600;

		$sql = 'SELECT a.id, a.datep, a.label, a.fk_soc, a.fk_contact';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
		$sql .= ' WHERE a.entity IN ('.getEntity('agenda').')';
		$sql .= ' AND a.fk_action IN ('.$db->sanitize(implode(',', $listeTypes)).')';
		$sql .= " AND a.datep >= '".$db->idate($debut)."'";
		$sql .= " AND a.datep < '".$db->idate($fin)."'";
		$sql .= ' ORDER BY a.datep';

		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = Sms123Api::t('Sms123SqlError', $db->lasterror());
			return -1;
		}

		$envoyes = 0;
		$ignores = 0;
		$detail = '';
		while ($obj = $db->fetch_object($resql)) {
			$origine = 'rappel-rdv#'.$obj->id;
			if (self::dejaEnvoye($db, $origine, 0)) {
				$ignores++;
				continue;
			}

			$numero = self::numeroEvenement($db, $obj->fk_contact, $obj->fk_soc);
			if (empty($numero)) {
				$ignores++;
				continue;
			}

			$message = strtr($modele, self::variablesEvenement($db, $obj));
			$code = Sms123Api::envoyer($numero, $message, 0, $origine, (int) $obj->fk_soc);
			if (in_array($code, array('80', '81'), true)) {
				$envoyes++;
			} else {
				$detail .= 'Evenement '.$obj->id.' : code '.$code.'. ';
			}
		}
		$db->free($resql);

		$this->output = Sms123Api::t('Sms123CronRdvDone', $envoyes, $ignores).' '.$detail;

		return 0;
	}

	/**
	 * Relances de factures impayees : SMS aux clients dont la facture est
	 * echue depuis X jours, avec repetition espacee et anti-doublon.
	 * A planifier une fois par jour (le matin).
	 *
	 * @return int 0 si OK
	 */
	public function relancesFactures()
	{
		global $db;

		$this->output = '';
		$this->error = '';
		dol_include_once('/sms123/class/sms123api.class.php');

		if (!getDolGlobalString('SMS123_RELANCE_ACTIF')) {
			$this->output = Sms123Api::t('Sms123CronRelanceOff');
			return 0;
		}

		$jours = (int) getDolGlobalString('SMS123_RELANCE_JOURS');
		if ($jours < 0) {
			$jours = 0;
		}
		$repeter = (int) getDolGlobalString('SMS123_RELANCE_REPETER');
		if ($repeter <= 0) {
			$repeter = 15;
		}
		$modele = getDolGlobalString('SMS123_RELANCE_TPL');
		if (empty($modele)) {
			$modele = '{masociete} : votre facture {ref} de {total} EUR est arrivee a echeance. Merci de regulariser.';
		}

		$limite = dol_now() - ($jours * 86400);

		$sql = 'SELECT f.rowid, f.ref, f.total_ttc, f.date_lim_reglement, f.fk_soc';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'facture as f';
		$sql .= ' WHERE f.entity IN ('.getEntity('facture').')';
		$sql .= ' AND f.fk_statut = 1 AND f.paye = 0';
		$sql .= ' AND f.date_lim_reglement IS NOT NULL';
		$sql .= " AND f.date_lim_reglement <= '".$db->idate($limite)."'";
		$sql .= ' ORDER BY f.date_lim_reglement';

		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = Sms123Api::t('Sms123SqlError', $db->lasterror());
			return -1;
		}

		$envoyes = 0;
		$ignores = 0;
		$detail = '';
		while ($obj = $db->fetch_object($resql)) {
			$origine = 'relance-facture#'.$obj->rowid;
			if (self::dejaEnvoye($db, $origine, $repeter)) {
				$ignores++;
				continue;
			}

			$numero = self::numeroTiers($db, $obj->fk_soc);
			if (empty($numero)) {
				$ignores++;
				continue;
			}

			$message = strtr($modele, array(
				'{ref}' => $obj->ref,
				'{total}' => price2num($obj->total_ttc, 'MT'),
				'{date}' => dol_print_date($db->jdate($obj->date_lim_reglement), 'day'),
				'{societe}' => self::nomTiers($db, $obj->fk_soc),
				'{masociete}' => self::maSociete(),
			));

			$code = Sms123Api::envoyer($numero, $message, 0, $origine, (int) $obj->fk_soc);
			if (in_array($code, array('80', '81'), true)) {
				$envoyes++;
			} else {
				$detail .= 'Facture '.$obj->ref.' : code '.$code.'. ';
			}
		}
		$db->free($resql);

		$this->output = Sms123Api::t('Sms123CronRelanceDone', $envoyes, $ignores).' '.$detail;

		return 0;
	}

	/**
	 * Alerte de solde bas : previent par e-mail (et par SMS si demande)
	 * lorsque le credit du compte passe sous le seuil configure.
	 * A planifier une fois par jour.
	 *
	 * @return int 0 si OK
	 */
	public function alerteSolde()
	{
		global $db, $conf;

		$this->output = '';
		$this->error = '';
		dol_include_once('/sms123/class/sms123api.class.php');

		if (!getDolGlobalString('SMS123_ALERTE_ACTIF')) {
			$this->output = Sms123Api::t('Sms123CronSoldeOff');
			return 0;
		}

		$seuil = (int) getDolGlobalString('SMS123_ALERTE_SEUIL');
		if ($seuil <= 0) {
			$seuil = 50;
		}
		$repeter = (int) getDolGlobalString('SMS123_ALERTE_REPETER');
		if ($repeter <= 0) {
			$repeter = 3;
		}

		$solde = Sms123Api::solde();
		if ($solde === null) {
			$this->output = Sms123Api::t('Sms123CronSoldeUnavailable');
			return 0;
		}
		if ($solde >= $seuil) {
			$this->output = Sms123Api::t('Sms123CronSoldeOk', $solde, $seuil);
			return 0;
		}

		$derniere = (int) getDolGlobalString('SMS123_ALERTE_DERNIERE');
		if ($derniere > 0 && (dol_now() - $derniere) < ($repeter * 86400)) {
			$this->output = Sms123Api::t('Sms123CronSoldeRecent');
			return 0;
		}

		$destinataire = getDolGlobalString('SMS123_ALERTE_MAIL');
		if (empty($destinataire)) {
			$destinataire = getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
		}
		$expediteur = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
		if (empty($expediteur)) {
			$expediteur = $destinataire;
		}

		if (!empty($destinataire) && !empty($expediteur)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
			$courriel = new CMailFile(
				Sms123Api::t('Sms123AlerteMailSubject', $solde),
				$destinataire,
				$expediteur,
				Sms123Api::t('Sms123AlerteMailBody', $solde, $seuil)
			);
			if (!$courriel->sendfile()) {
				dol_syslog('Sms123Cron::alerteSolde envoi e-mail impossible : '.$courriel->error, LOG_WARNING);
			}
		}

		if (getDolGlobalString('SMS123_ALERTE_SMS')) {
			$numero = getDolGlobalString('SMS123_NUM_ADMIN');
			if (!empty($numero)) {
				Sms123Api::envoyer($numero, Sms123Api::t('Sms123AlerteSmsBody', $solde), 0, 'alerte-solde');
			}
		}

		dolibarr_set_const($db, 'SMS123_ALERTE_DERNIERE', dol_now(), 'chaine', 0, '', $conf->entity);
		$this->output = Sms123Api::t('Sms123CronSoldeSent', $solde);

		return 0;
	}

	/* ------------------------------------------------ utilitaires */

	/**
	 * Un SMS a-t-il deja ete envoye pour cette origine ?
	 *
	 * @param DoliDB $db      base
	 * @param string $origine identifiant d origine (ex. relance-facture#12)
	 * @param int    $jours   0 = jamais deux fois ; sinon fenetre en jours
	 * @return bool
	 */
	public static function dejaEnvoye($db, $origine, $jours = 0)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."sms123_envoi WHERE origine = '".$db->escape($origine)."'";
		if ($jours > 0) {
			$sql .= " AND datec > '".$db->idate(dol_now() - ($jours * 86400))."'";
		}
		$sql .= ' LIMIT 1';

		$resql = $db->query($sql);
		if (!$resql) {
			return false;
		}
		$trouve = ($db->num_rows($resql) > 0);
		$db->free($resql);

		return $trouve;
	}

	/** Numero de mobile : contact de l evenement en priorite, sinon tiers. */
	public static function numeroEvenement($db, $fk_contact, $fk_soc)
	{
		if ($fk_contact > 0) {
			$sql = 'SELECT phone_mobile, phone_perso, phone_pro FROM '.MAIN_DB_PREFIX.'socpeople WHERE rowid = '.((int) $fk_contact);
			$resql = $db->query($sql);
			if ($resql && ($obj = $db->fetch_object($resql))) {
				foreach (array('phone_mobile', 'phone_perso', 'phone_pro') as $champ) {
					if (!empty($obj->$champ)) {
						$db->free($resql);
						return $obj->$champ;
					}
				}
			}
			if ($resql) {
				$db->free($resql);
			}
		}

		return self::numeroTiers($db, $fk_soc);
	}

	/** Numero de mobile d un tiers. */
	public static function numeroTiers($db, $fk_soc)
	{
		if ($fk_soc <= 0) {
			return '';
		}
		$sql = 'SELECT phone FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.((int) $fk_soc);
		$resql = $db->query($sql);
		$numero = '';
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$numero = empty($obj->phone) ? '' : $obj->phone;
		}
		if ($resql) {
			$db->free($resql);
		}

		return $numero;
	}

	/** Nom du tiers. */
	public static function nomTiers($db, $fk_soc)
	{
		if ($fk_soc <= 0) {
			return '';
		}
		$sql = 'SELECT nom FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.((int) $fk_soc);
		$resql = $db->query($sql);
		$nom = '';
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$nom = $obj->nom;
		}
		if ($resql) {
			$db->free($resql);
		}

		return $nom;
	}

	/** Raison sociale de l entreprise. */
	public static function maSociete()
	{
		global $mysoc;

		return is_object($mysoc) ? $mysoc->name : '';
	}

	/** Variables de substitution d un evenement d agenda. */
	public static function variablesEvenement($db, $obj)
	{
		return array(
			'{date}' => dol_print_date($db->jdate($obj->datep), 'day'),
			'{heure}' => dol_print_date($db->jdate($obj->datep), 'hour'),
			'{label}' => empty($obj->label) ? '' : $obj->label,
			'{societe}' => self::nomTiers($db, $obj->fk_soc),
			'{masociete}' => self::maSociete(),
		);
	}
}
