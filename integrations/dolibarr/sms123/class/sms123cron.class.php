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
	/** Journal detaille de la derniere execution (une ligne par etape). */
	public $journal = array();

	/**
	 * Consigne une etape : dans le journal de l'objet ET dans le syslog de
	 * Dolibarr, pour qu'une execution automatique laisse une trace lisible.
	 *
	 * @param string $texte  ligne de journal
	 * @param int    $niveau niveau de log Dolibarr
	 * @return void
	 */
	protected function tracer($texte, $niveau = LOG_INFO)
	{
		$this->journal[] = $texte;
		dol_syslog('Sms123Cron : '.$texte, $niveau);
	}

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
	public function rappelsRendezVous($test = 0, $max = 200)
	{
		global $db;

		$this->output = '';
		$this->error = '';
		$this->journal = array();
		dol_include_once('/sms123/class/sms123api.class.php');

		$this->tracer('rappels de rendez-vous : demarrage'.(empty($test) ? '' : ' EN SIMULATION'));

		if (!getDolGlobalString('SMS123_RDV_ACTIF')) {
			$this->output = Sms123Api::t('Sms123CronRdvOff');
			$this->tracer($this->output, LOG_WARNING);
			return 0;
		}

		$selection = self::candidatsRappels($db, $max);
		if ($selection['erreur'] !== '') {
			$this->error = $selection['erreur'];
			$this->tracer('erreur de selection : '.$selection['erreur'], LOG_ERR);
			return -1;
		}

		$this->tracer('criteres : '.(empty($selection['tous'])
				? count($selection['types']).' type(s) coche(s)' : 'tous les types')
			.', fenetre de '.$selection['heures'].' h, '
			.count($selection['lignes']).' evenement(s) trouve(s)');

		// Aucun candidat : on dit ce qui vient ensuite, sinon le zero reste muet
		if (!count($selection['lignes'])) {
			if (!count($selection['prochains'])) {
				$this->tracer('aucun evenement d agenda dans les 7 prochains jours');
			}
			foreach ($selection['prochains'] as $proche) {
				$raisons = array();
				if (!$proche['dans_fenetre']) {
					$raisons[] = 'hors fenetre de '.$selection['heures'].' h';
				}
				if (!$proche['type_ok']) {
					$raisons[] = 'type non retenu';
				}
				$this->tracer('a venir : evenement '.$proche['id'].' le '
					.dol_print_date($proche['datep'], 'dayhour').' (dans '.$proche['delai'].')'
					.(count($raisons) ? ' -> ecarte : '.implode(', ', $raisons) : ''));
			}
		}

		if (!count($selection['types']) && empty($selection['tous']) && !count($selection['lignes'])) {
			$this->output = Sms123Api::t('Sms123CronRdvNoType');
			$this->tracer($this->output, LOG_WARNING);
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
			$prefixe = 'evenement '.$ligne['id'].' ('.dol_print_date($ligne['datep'], 'dayhour').')';

			if ($ligne['etat'] != 'a-envoyer') {
				$ignores++;
				$this->tracer($prefixe.' : ignore, '
					.($ligne['etat'] == 'deja' ? 'rappel deja envoye' : 'aucun numero trouve'));
				continue;
			}

			$message = strtr($modele, self::variablesEvenement($db, $ligne['objet']));
			$this->tracer($prefixe.' : envoi au '.$ligne['numero']
				.' (champ '.$ligne['source'].') : "'.dol_trunc($message, 80).'"');

			$code = Sms123Api::envoyer($ligne['numero'], $message, $test,
				'rappel-rdv#'.$ligne['id'], (int) $ligne['objet']->fk_soc);

			if (Sms123Api::estSucces($code, $test)) {
				$envoyes++;
				$this->tracer($prefixe.' : reponse '.$code.' - '.Sms123Api::libelle($code));
			} else {
				$detail .= 'Evenement '.$ligne['id'].' : code '.$code.'. ';
				$this->tracer($prefixe.' : ECHEC, reponse '.$code.' - '.Sms123Api::libelle($code), LOG_ERR);
			}
		}

		$this->output = Sms123Api::t('Sms123CronRdvDone', $envoyes, $ignores).' '.$detail;
		$this->tracer('fin : '.$this->output);

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

		$resultat = array('erreur' => '', 'heures' => 24, 'types' => array(),
			'tous' => getDolGlobalString('SMS123_RDV_TOUS') ? 1 : 0, 'lignes' => array());

		foreach (explode(',', getDolGlobalString('SMS123_RDV_TYPES')) as $t) {
			$t = (int) trim($t);
			if ($t > 0) {
				$resultat['types'][] = $t;
			}
		}
		// Ni type coche, ni « tous les types » : seules restent les fiches ou
		// le rappel a ete active a la main, la requete les retrouvera.
		if (!count($resultat['types']) && empty($resultat['tous'])
			&& !self::forcagesExistent($db)) {
			return $resultat;
		}

		$heures = (int) getDolGlobalString('SMS123_RDV_HEURES');
		if ($heures <= 0) {
			$heures = 24;
		}
		$resultat['heures'] = $heures;

		$maintenant = dol_now();
		$debut = $db->idate($maintenant);
		$fin = $db->idate($maintenant + ($heures * 3600));

		// Le choix propre a une fiche (table sms123_rdv) prime sur le type :
		//   actif = 0 : l'evenement est ecarte meme si son type est coche
		//   actif = 1 : l'evenement est retenu meme si son type ne l'est pas
		$conditions = array('r.actif = 1');
		if (!empty($resultat['tous'])) {
			$conditions[] = '(r.actif IS NULL OR r.actif = 1)';
		} elseif (count($resultat['types'])) {
			$conditions[] = '(a.fk_action IN ('.$db->sanitize(implode(',', $resultat['types'])).')'
				.' AND (r.actif IS NULL OR r.actif = 1))';
		}

		$sql = 'SELECT a.id, a.datep, a.label, a.fk_soc, a.fk_contact, r.actif as forcage';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'sms123_rdv as r ON r.fk_actioncomm = a.id';
		$sql .= ' WHERE a.entity IN ('.getEntity('agenda').')';
		$sql .= " AND a.datep >= '".$debut."'";
		$sql .= " AND a.datep <= '".$fin."'";
		$sql .= ' AND ('.implode(' OR ', $conditions).')';
		$sql .= ' ORDER BY a.datep';
		$sql .= $db->plimit($max, 0);

		$resql = $db->query($sql);

		// Repli : module mis a jour sans reactivation, la table des choix
		// par fiche n'existe pas encore. On retombe sur le seul filtre par type.
		if (!$resql && (count($resultat['types']) || !empty($resultat['tous']))) {
			$sql = 'SELECT a.id, a.datep, a.label, a.fk_soc, a.fk_contact, NULL as forcage';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
			$sql .= ' WHERE a.entity IN ('.getEntity('agenda').')';
			if (empty($resultat['tous'])) {
				$sql .= ' AND a.fk_action IN ('.$db->sanitize(implode(',', $resultat['types'])).')';
			}
			$sql .= " AND a.datep >= '".$debut."'";
			$sql .= " AND a.datep <= '".$fin."'";
			$sql .= ' ORDER BY a.datep';
			$sql .= $db->plimit($max, 0);
			$resql = $db->query($sql);
			dol_syslog('Sms123Cron : table sms123_rdv absente, reactivez le module pour '
				.'utiliser la case « Rappel SMS » des fiches evenement.', LOG_WARNING);
		}

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
				'forcage' => ($obj->forcage === null ? null : (int) $obj->forcage),
				'objet' => $obj,
			);
		}
		$db->free($resql);

		// Pour le diagnostic : ce qui vient juste apres, afin qu'un « aucun
		// evenement » s'explique de lui-meme (hors fenetre, type non retenu...)
		$resultat['prochains'] = self::prochainsEvenements($db, $resultat);

		return $resultat;
	}

	/**
	 * Prochains evenements de l'agenda, tous types confondus, avec la raison
	 * pour laquelle ils sont retenus ou non. Sert uniquement au diagnostic.
	 *
	 * @param DoliDB $db        base
	 * @param array  $selection resultat partiel de candidatsRappels()
	 * @param int    $limite    nombre de lignes
	 * @param int    $jours     horizon d'observation
	 * @return array            id, datep, label, delai, dans_fenetre, type_ok
	 */
	public static function prochainsEvenements($db, $selection, $limite = 5, $jours = 7)
	{
		$maintenant = dol_now();
		$liste = array();

		$sql = 'SELECT a.id, a.datep, a.label, a.fk_action';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'actioncomm as a';
		$sql .= ' WHERE a.entity IN ('.getEntity('agenda').')';
		$sql .= " AND a.datep >= '".$db->idate($maintenant)."'";
		$sql .= " AND a.datep <= '".$db->idate($maintenant + ($jours * 86400))."'";
		$sql .= ' ORDER BY a.datep';
		$sql .= $db->plimit($limite, 0);

		$resql = $db->query($sql);
		if (!$resql) {
			return $liste;
		}

		$fin = $maintenant + ($selection['heures'] * 3600);
		while ($obj = $db->fetch_object($resql)) {
			$date = $db->jdate($obj->datep);
			$liste[] = array(
				'id' => (int) $obj->id,
				'datep' => $date,
				'label' => empty($obj->label) ? '' : $obj->label,
				'delai' => self::delaiTexte($date - $maintenant),
				'dans_fenetre' => ($date <= $fin),
				'type_ok' => (!empty($selection['tous']) || self::typeConcerne($db, $obj->fk_action)),
			);
		}
		$db->free($resql);

		return $liste;
	}

	/** Duree lisible : « 24 h 02 min », « 3 j 05 h 10 min ». */
	public static function delaiTexte($secondes)
	{
		$secondes = max(0, (int) $secondes);
		$heures = (int) floor($secondes / 3600);
		$minutes = (int) floor(($secondes % 3600) / 60);

		if ($heures >= 24) {
			$jours = (int) floor($heures / 24);

			return $jours.' j '.sprintf('%02d', $heures % 24).' h '.sprintf('%02d', $minutes).' min';
		}

		return $heures.' h '.sprintf('%02d', $minutes).' min';
	}

	/* --------------------- declencheur de secours (« cron sur visite ») */

	/**
	 * Le cron de Dolibarr tourne-t-il vraiment ? On regarde la derniere
	 * execution des taches du module.
	 *
	 * @param DoliDB $db     base
	 * @param int    $heures anciennete acceptee
	 * @return bool
	 */
	public static function cronDolibarrActif($db, $heures = 3)
	{
		$sql = 'SELECT datelastrun FROM '.MAIN_DB_PREFIX."cronjob";
		$sql .= " WHERE objectname = 'Sms123Cron' AND datelastrun IS NOT NULL";
		$sql .= " AND datelastrun > '".$db->idate(dol_now() - ($heures * 3600))."'";
		$sql .= ' LIMIT 1';

		$resql = $db->query($sql);
		if (!$resql) {
			return false;
		}
		$actif = ($db->num_rows($resql) > 0);
		$db->free($resql);

		return $actif;
	}

	/**
	 * Declencheur de secours : lance les taches du module pendant l'affichage
	 * d'une page, lorsqu'aucun cron n'est en service.
	 *
	 * C'est un depannage, pas un remplacement : rien ne part tant que personne
	 * n'ouvre Dolibarr. Le verrou (date posee AVANT le travail) evite que deux
	 * visiteurs simultanes lancent la meme chose.
	 *
	 * @param DoliDB $db base
	 * @return int 1 si une execution a eu lieu
	 */
	public static function declencheurDeSecours($db)
	{
		global $conf;

		if (!getDolGlobalString('SMS123_CRON_SECOURS')) {
			return 0;
		}

		$intervalle = (int) getDolGlobalString('SMS123_CRON_SECOURS_MIN');
		if ($intervalle <= 0) {
			$intervalle = 15;
		}

		$maintenant = dol_now();
		$dernier = (int) getDolGlobalString('SMS123_CRON_SECOURS_TS');
		if ($dernier > 0 && ($maintenant - $dernier) < ($intervalle * 60)) {
			return 0;
		}

		// Un vrai cron s'en occupe deja : on ne double pas le travail
		if (self::cronDolibarrActif($db)) {
			return 0;
		}

		// Verrou pose avant le travail
		dolibarr_set_const($db, 'SMS123_CRON_SECOURS_TS', $maintenant, 'chaine', 0, '', $conf->entity);

		$tache = new self();
		$tache->rappelsRendezVous(0, 20);
		$compte = $tache->output;

		// Les taches quotidiennes ne sont lancees qu'une fois par jour
		$jour = (int) getDolGlobalString('SMS123_CRON_SECOURS_JOUR');
		if ($jour <= 0 || ($maintenant - $jour) > 86400) {
			dolibarr_set_const($db, 'SMS123_CRON_SECOURS_JOUR', $maintenant, 'chaine', 0, '', $conf->entity);
			$tache->relancesFactures();
			$compte .= ' | '.$tache->output;
			$tache->alerteSolde();
			$compte .= ' | '.$tache->output;
		}

		dolibarr_set_const($db, 'SMS123_CRON_SECOURS_RESULTAT', dol_trunc($compte, 250), 'chaine', 0, '', $conf->entity);
		dol_syslog('Sms123Cron : declencheur de secours -> '.$compte, LOG_INFO);

		return 1;
	}

	/* --------------------- choix « Rappel SMS » propre a une fiche */

	/**
	 * Existe-t-il au moins un evenement dont le rappel a ete active a la main
	 * sur sa fiche ? Sert a decider s'il faut interroger l'agenda alors
	 * qu'aucun type n'est coche dans la configuration.
	 *
	 * @param DoliDB $db base
	 * @return bool
	 */
	public static function forcagesExistent($db)
	{
		$resql = $db->query('SELECT fk_actioncomm FROM '.MAIN_DB_PREFIX.'sms123_rdv WHERE actif = 1 LIMIT 1');
		if (!$resql) {
			return false;
		}
		$trouve = ($db->num_rows($resql) > 0);
		$db->free($resql);

		return $trouve;
	}

	/**
	 * Le type d'evenement est-il coche dans la configuration du module ?
	 *
	 * @param DoliDB $db      base
	 * @param int    $type_id identifiant du type (actioncomm.fk_action)
	 * @return bool
	 */
	public static function typeConcerne($db, $type_id)
	{
		$type_id = (int) $type_id;
		if ($type_id <= 0) {
			return false;
		}
		foreach (explode(',', getDolGlobalString('SMS123_RDV_TYPES')) as $t) {
			if ((int) trim($t) === $type_id) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Choix enregistre sur la fiche d'un evenement.
	 *
	 * @param DoliDB $db base
	 * @param int    $id evenement
	 * @return int|null  null = aucun choix (comportement automatique), 0 ou 1
	 */
	public static function forcageEvenement($db, $id)
	{
		$id = (int) $id;
		if ($id <= 0) {
			return null;
		}
		$resql = $db->query('SELECT actif FROM '.MAIN_DB_PREFIX.'sms123_rdv WHERE fk_actioncomm = '.$id);
		if (!$resql) {
			return null;
		}
		$valeur = null;
		if ($obj = $db->fetch_object($resql)) {
			$valeur = (int) $obj->actif;
		}
		$db->free($resql);

		return $valeur;
	}

	/**
	 * Enregistre un choix explicite pour un evenement.
	 *
	 * @param DoliDB $db     base
	 * @param int    $id     evenement
	 * @param int    $valeur 1 = envoyer, 0 = ne pas envoyer
	 * @return int
	 */
	public static function enregistrerForcage($db, $id, $valeur)
	{
		global $conf;

		$id = (int) $id;
		if ($id <= 0) {
			return -1;
		}
		self::supprimerForcage($db, $id);

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'sms123_rdv(fk_actioncomm, entity, actif, datec)';
		$sql .= ' VALUES ('.$id.', '.((int) $conf->entity).', '.($valeur ? 1 : 0).", '".$db->idate(dol_now())."')";

		if (!$db->query($sql)) {
			dol_syslog('Sms123Cron::enregistrerForcage '.$db->lasterror(), LOG_WARNING);
			return -1;
		}

		return 1;
	}

	/** Revient au comportement automatique pour un evenement. */
	public static function supprimerForcage($db, $id)
	{
		$id = (int) $id;
		if ($id <= 0) {
			return -1;
		}

		return $db->query('DELETE FROM '.MAIN_DB_PREFIX.'sms123_rdv WHERE fk_actioncomm = '.$id) ? 1 : -1;
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
			if (Sms123Api::estSucces($code)) {
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
