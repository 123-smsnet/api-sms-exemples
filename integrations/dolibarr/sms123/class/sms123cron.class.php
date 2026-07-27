<?php
/* Taches planifiees du module 123-SMS - licence MIT
 * - rappelsRendezVous() : SMS de rappel avant les evenements d'agenda
 * - relancesFactures()  : SMS de relance sur les factures impayees
 * Les deux sont declarees dans Accueil > Configuration > Taches planifiees.
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
		global $db, $conf, $langs;

		$this->output = '';
		$this->error = '';

		if (!getDolGlobalString('SMS123_RDV_ACTIF')) {
			$this->output = 'Rappels de rendez-vous desactives dans la configuration du module.';
			return 0;
		}

		$types = getDolGlobalString('SMS123_RDV_TYPES');
		if (empty($types)) {
			$this->output = 'Aucun type d evenement selectionne : rien a faire.';
			return 0;
		}
		$listeTypes = array();
		foreach (explode(',', $types) as $t) {
			$t = (int) trim($t);
			if ($t > 0) {
				$listeTypes[] = $t;
			}
		}
		if (!count($listeTypes)) {
			$this->output = 'Aucun type d evenement valide : rien a faire.';
			return 0;
		}

		dol_include_once('/sms123/class/sms123api.class.php');

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
			$this->error = 'Erreur SQL : '.$db->lasterror();
			return -1;
		}

		$envoyes = 0;
		$ignores = 0;
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
			$code = Sms123Api::envoyer($numero, $message, 0, $origine);
			if (in_array($code, array('80', '81'), true)) {
				$envoyes++;
			} else {
				$this->output .= 'Evenement '.$obj->id.' : code '.$code.'. ';
			}
		}
		$db->free($resql);

		$this->output = 'Rappels de rendez-vous : '.$envoyes.' SMS envoye(s), '.$ignores
			.' ignore(s) (deja rappele ou sans numero). '.$this->output;

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
		global $db, $conf;

		$this->output = '';
		$this->error = '';

		if (!getDolGlobalString('SMS123_RELANCE_ACTIF')) {
			$this->output = 'Relances de factures desactivees dans la configuration du module.';
			return 0;
		}

		dol_include_once('/sms123/class/sms123api.class.php');

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
		$sql .= " AND f.date_lim_reglement IS NOT NULL";
		$sql .= " AND f.date_lim_reglement <= '".$db->idate($limite)."'";
		$sql .= ' ORDER BY f.date_lim_reglement';

		$resql = $db->query($sql);
		if (!$resql) {
			$this->error = 'Erreur SQL : '.$db->lasterror();
			return -1;
		}

		$envoyes = 0;
		$ignores = 0;
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

			$code = Sms123Api::envoyer($numero, $message, 0, $origine);
			if (in_array($code, array('80', '81'), true)) {
				$envoyes++;
			} else {
				$this->output .= 'Facture '.$obj->ref.' : code '.$code.'. ';
			}
		}
		$db->free($resql);

		$this->output = 'Relances de factures : '.$envoyes.' SMS envoye(s), '.$ignores
			.' ignore(s) (deja relance ou sans numero). '.$this->output;

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
