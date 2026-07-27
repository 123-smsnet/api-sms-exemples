<?php
/* Hooks du module 123-SMS - licence MIT
 * - bouton « Envoyer un SMS » sur les fiches tiers, contact, devis,
 *   commande, facture et expedition ;
 * - action de masse « Envoyer un SMS » sur les listes de tiers et de contacts.
 */

class ActionsSms123
{
	public $db;
	public $results = array();
	public $resprints;
	public $errors = array();

	/** Contextes ou le bouton d'action est propose. */
	const FICHES = 'thirdpartycard,contactcard,propalcard,ordercard,invoicecard,expeditioncard';
	/** Contextes ou l'action de masse est proposee. */
	const LISTES = 'thirdpartylist,contactlist';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Ajoute le bouton dans la barre d actions des fiches.
	 *
	 * @param array  $parameters  contexte du hook
	 * @param object $object      objet affiche
	 * @param string $action      action en cours
	 * @param object $hookmanager gestionnaire de hooks
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!$this->autorise() || !$this->contexte($parameters, self::FICHES)) {
			return 0;
		}

		dol_include_once('/sms123/class/sms123api.class.php');
		Sms123Api::langs();

		$numero = $this->numero($object);
		$message = $this->messagePropose($object);
		$socid = $this->socid($object);

		$url = dol_buildpath('/sms123/sms123index.php', 1)
			.'?numero='.urlencode($numero)
			.'&message='.urlencode($message)
			.'&socid='.((int) $socid);

		if (empty($numero)) {
			print '<span class="butActionRefused classfortooltip" title="'
				.dol_escape_htmltag($langs->transnoentities('Sms123MassNoNumber')).'">'
				.$langs->transnoentities('Sms123BoxSendOne').'</span>';
		} else {
			print '<a class="butAction" href="'.$url.'">'.$langs->transnoentities('Sms123BoxSendOne').'</a>';
		}

		return 0;
	}

	/**
	 * Ajoute « Envoyer un SMS » dans la liste deroulante des actions de masse
	 * des listes de tiers et de contacts.
	 *
	 * @param array  $parameters  contexte du hook
	 * @param object $object      objet courant
	 * @param string $action      action en cours
	 * @param object $hookmanager gestionnaire de hooks
	 * @return int
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!$this->autorise() || !$this->contexte($parameters, self::LISTES)) {
			return 0;
		}

		dol_include_once('/sms123/class/sms123api.class.php');
		Sms123Api::langs();

		$this->resprints = '<option value="sms123_masse">'
			.dol_escape_htmltag($langs->transnoentities('Sms123MassAction')).'</option>';

		return 0;
	}

	/**
	 * Traite l action de masse : memorise la selection puis renvoie vers la
	 * page d envoi en masse, ou l utilisateur redige son message.
	 *
	 * @param array  $parameters  contexte du hook (dont toselect et massaction)
	 * @param object $object      objet courant
	 * @param string $action      action en cours
	 * @param object $hookmanager gestionnaire de hooks
	 * @return int
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		$massaction = empty($parameters['massaction'])
			? GETPOST('massaction', 'alpha') : $parameters['massaction'];
		if ($massaction != 'sms123_masse' && $action != 'sms123_masse') {
			return 0;
		}
		if (!$this->autorise()) {
			return 0;
		}

		$contextes = explode(':', empty($parameters['context']) ? '' : $parameters['context']);
		$type = in_array('contactlist', $contextes, true) ? 'contact' : 'societe';

		$selection = empty($parameters['toselect']) ? GETPOST('toselect', 'array') : $parameters['toselect'];
		$ids = array();
		if (is_array($selection)) {
			foreach ($selection as $id) {
				$id = (int) $id;
				if ($id > 0) {
					$ids[] = $id;
				}
			}
		}

		$_SESSION['sms123_masse'] = array('type' => $type, 'ids' => $ids);

		header('Location: '.dol_buildpath('/sms123/sms123masse.php', 1));
		exit;
	}

	/* ------------------------------------------------ utilitaires */

	/** Le module est-il actif et l utilisateur habilite ? */
	protected function autorise()
	{
		global $user;

		return isModEnabled('sms123') && is_object($user) && $user->hasRight('sms123', 'envoyer');
	}

	/** Le hook est-il appele depuis l un des contextes attendus ? */
	protected function contexte($parameters, $liste)
	{
		$contextes = explode(':', empty($parameters['context']) ? '' : $parameters['context']);

		return count(array_intersect($contextes, explode(',', $liste))) > 0;
	}

	/** Numero de telephone deduit de la fiche affichee. */
	protected function numero($object)
	{
		foreach (array('phone_mobile', 'phone_perso', 'phone_pro', 'phone') as $champ) {
			if (!empty($object->$champ)) {
				return $object->$champ;
			}
		}

		if (method_exists($object, 'fetch_thirdparty')) {
			$object->fetch_thirdparty();
			if (!empty($object->thirdparty)) {
				$soc = $object->thirdparty;
				foreach (array('phone_mobile', 'phone') as $champ) {
					if (!empty($soc->$champ)) {
						return $soc->$champ;
					}
				}
			}
		}

		return '';
	}

	/** Tiers concerne par la fiche (pour la trace dans l agenda). */
	protected function socid($object)
	{
		if (!empty($object->element) && $object->element == 'societe') {
			return empty($object->id) ? 0 : (int) $object->id;
		}
		if (!empty($object->socid)) {
			return (int) $object->socid;
		}
		if (!empty($object->fk_soc)) {
			return (int) $object->fk_soc;
		}
		if (!empty($object->thirdparty) && !empty($object->thirdparty->id)) {
			return (int) $object->thirdparty->id;
		}

		return 0;
	}

	/** Message pre-rempli selon le type de document. */
	protected function messagePropose($object)
	{
		global $mysoc;

		$moi = is_object($mysoc) ? $mysoc->name : '';
		$ref = empty($object->ref) ? '' : $object->ref;
		$element = empty($object->element) ? '' : $object->element;

		if ($element == 'facture') {
			return $moi.' : au sujet de votre facture '.$ref.'.';
		}
		if ($element == 'commande') {
			return $moi.' : au sujet de votre commande '.$ref.'.';
		}
		if ($element == 'propal') {
			return $moi.' : au sujet de votre devis '.$ref.'.';
		}
		if ($element == 'shipping') {
			return $moi.' : au sujet de votre expedition '.$ref.'.';
		}

		return '';
	}
}
