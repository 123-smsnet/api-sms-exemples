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
	 * Rappels de rendez-vous : un SMS par evenement d'agenda dont le type est
	 * coche dans la configuration, des qu'il entre dans la fenetre choisie.
	 *
	 * La selection est faite par candidatsRappels(), utilisee telle quelle par
	 * le bouton « Tester la selection » de la configuration : ce qui est
	 * affiche la est exactement ce que cette tache enverra.
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

		$selection = self::candidatsRappels($db);
		if ($selection['erreur'] !== '') {
			$this->error = $selection['erreur'];
			return -1;
		}
		if (!count($selection['types'])) {
			$this->output = Sms123Api::t('Sms123CronRdvNoType');
			return 0;
		}

		$modele = getDolGlobalString('SMS123_RDV_TPL');
		if (empty($modele)) {
			$modele = 'Rappel : rendez-vous le {date} a {heure}. {masociete}';
		}

		$envoyes = 0;
		$ignores = 0;
		$detail = '';
		foreach ($selection['lignes'] as $ligne) {
			if ($ligne['etat'] != 'a-envoyer') {
				$ignores++;
				continue;
			}

			$message = strtr($modele, self::variablesEvenement($db, $ligne['objet']));
			$code = Sms123Api::envoyer($ligne['numero'], $message, 0,
				'rappel-rdv#'.$ligne['id'], (int) $ligne['objet']->fk_soc);
			if (in_array($code, array('80', '81'), true)) {
				$envoyes++;
			} else {
				$detail .= 'Evenement '.$ligne['id'].' : code '.$code.'. ';
			}
		}

		$this->output = Sms123Api::t('Sms123CronRdvDone', $envoyes, $ignores).' '.$detail;

		return 0;
	}

	/**
	 * Evenements d'agenda qui entrent dans la fenetre de rappel, avec le
	 * numero trouve pour chacun et son etat.
	 *
	 * Fenetre : tout evenement qui commence entre maintenant et maintenant +
	 * X heures. Un evenement deja rappele est ignore (une seule fois par
	 * evenement), ce qui rend la tache insensible a l'heure exacte a laquelle
	 * elle tourne : qu'elle s'execute toutes les heures ou trois fois par
	 * jour, aucun rendez-vous n'est saute.
	 *
	 * @param DoliDB $db  base
	 * @param int    $max nombre maximum d'evenements examines
	 * @return array      erreur, heures, types, lignes (id, datep, label,
	 *                    societe, numero, source, etat, objet)
	 */
	public static function candidatsRappels($db, $max = 200)
	{
		dol_include_once('/sms123/class/sms123api.class.php');

		$resultat = array('erreur' => '', 'heures' => 24, 'types' => array(), 'lignes' => array());

		foreach (explode(',', getDolGlobalString('SMS123_RDV_TYPES')) as $t) {
			$t = (int) trim($t);
			if ($t > 0) {
				$resultat['types'][] = $t;
			}
		}
		if (!count($resultat['types'])) {
			return $resultat;
		}

		$heures = (int) getDolGlobalString('SMS123_RDV_HEURES');
		if ($heures <= 0) {
			$heures = 24;
		}
		$resultat['heures'] = $heures;

		$maintenant = dol_now();
		$sql = 'SELECT a.id, a.datep, a.label, a.fk_soc, a.fk_contact';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
		$sql .= ' WHERE a.entity IN ('.getEntity('agenda').')';
		$sql .= ' AND a.fk_action IN ('.$db->sanitize(implode(',', $resultat['types'])).')';
		$sql .= " AND a.datep >= '".$db->idate($maintenant)."'";
		$sql .= " AND a.datep <= '".$db->idate($maintenant + ($heures * 3600))."'";
		$sql .= ' ORDER BY a.datep';
		$sql .= $db->plimit($max, 0);

		$resql = $db->query($sql);
		if (!$resql) {
			$resultat['erreur'] = Sms123Api::t('Sms123SqlError', $db->lasterror());
			return $resultat;
		}

		while ($obj = $db->fetch_object($resql)) {
			$trouve = self::numeroEvenement($db, $obj->fk_contact, $obj->fk_soc, $obj->id);
			if (self::dejaEnvoye($db, 'rappel-rdv#'.$obj->id, 0)) {
				$etat = 'deja';
			} elseif ($trouve['numero'] === '') {
				$etat = 'sans-numero';
			} else {
				$etat = 'a-envoyer';
			}

			$resultat['lignes'][] = array(
				'id' => (int) $obj->id,
				'datep' => $db->jdate($obj->datep),
				'label' => empty($obj->label) ? '' : $obj->label,
				'societe' => self::nomTiers($db, $obj->fk_soc),
				'numero' => $trouve['numero'],
				'source' => $trouve['source'],
				'etat' => $etat,
				'objet' => $obj,
			);
		}
		$db->free($resql);

		return $resultat;
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

			$trouve = self::numeroTiers($db, $obj->fk_soc);
			if ($trouve['numero'] === '') {
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

			$code = Sms123Api::envoyer($trouve['numero'], $message, 0, $origine, (int) $obj->fk_soc);
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

	/**
	 * Numero de mobile d'un evenement d'agenda, dans cet ordre :
	 *   1. contact lie a l'evenement    : mobile, perso, professionnel
	 *   2. tiers lie a l'evenement      : mobile, telephone
	 *
	 * Le contact est cherche sur le champ historique fk_contact ET dans la
	 * table des ressources de l'evenement (llx_actioncomm_resources), ou
	 * Dolibarr enregistre les contacts depuis la version 9.
	 *
	 * @param DoliDB $db          base
	 * @param int    $fk_contact  contact historique de l evenement
	 * @param int    $fk_soc      tiers de l evenement
	 * @param int    $id          identifiant de l evenement
	 * @return array              numero, source (champ utilise)
	 */
	public static function numeroEvenement($db, $fk_contact, $fk_soc, $id = 0)
	{
		$champs = array(
			'phone_mobile' => 'contact.phone_mobile',
			'phone_perso' => 'contact.phone_perso',
			'phone_pro' => 'contact.phone_pro',
		);

		// 1a. contact historique de l evenement
		if ($fk_contact > 0) {
			$sql = 'SELECT phone_mobile, phone_perso, phone_pro FROM '.MAIN_DB_PREFIX.'socpeople';
			$sql .= ' WHERE rowid = '.((int) $fk_contact);
			$resql = $db->query($sql);
			if ($resql && ($obj = $db->fetch_object($resql))) {
				foreach ($champs as $champ => $source) {
					if (!empty($obj->$champ)) {
						$db->free($resql);
						return array('numero' => $obj->$champ, 'source' => $source);
					}
				}
			}
			if ($resql) {
				$db->free($resql);
			}
		}

		// 1b. contacts declares comme ressources de l evenement
		if ($id > 0) {
			$sql = 'SELECT sp.phone_mobile, sp.phone_perso, sp.phone_pro';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm_resources as ar';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'socpeople as sp ON sp.rowid = ar.fk_element';
			$sql .= ' WHERE ar.fk_actioncomm = '.((int) $id);
			$sql .= " AND ar.element_type = 'socpeople'";
			$sql .= ' ORDER BY ar.rowid';
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					foreach ($champs as $champ => $source) {
						if (!empty($obj->$champ)) {
							$db->free($resql);
							return array('numero' => $obj->$champ, 'source' => $source);
						}
					}
				}
				$db->free($resql);
			}
		}

		// 2. tiers de l evenement
		return self::numeroTiers($db, $fk_soc);
	}

	/**
	 * Numero de mobile d'un tiers : champ « Mobile » en priorite, sinon
	 * « Telephone ». La colonne phone_mobile n'existe pas sur toutes les
	 * versions de Dolibarr, d'ou la requete de repli.
	 *
	 * @param DoliDB $db     base
	 * @param int    $fk_soc tiers
	 * @return array         numero, source (champ utilise)
	 */
	public static function numeroTiers($db, $fk_soc)
	{
		$vide = array('numero' => '', 'source' => '');
		if ($fk_soc <= 0) {
			return $vide;
		}

		$sql = 'SELECT phone, phone_mobile FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.((int) $fk_soc);
		$resql = $db->query($sql);
		if (!$resql) {
			$sql = 'SELECT phone FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.((int) $fk_soc);
			$resql = $db->query($sql);
		}
		if (!$resql) {
			return $vide;
		}

		$trouve = $vide;
		if ($obj = $db->fetch_object($resql)) {
			if (!empty($obj->phone_mobile)) {
				$trouve = array('numero' => $obj->phone_mobile, 'source' => 'tiers.phone_mobile');
			} elseif (!empty($obj->phone)) {
				$trouve = array('numero' => $obj->phone, 'source' => 'tiers.phone');
			}
		}
		$db->free($resql);

		return $trouve;
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
