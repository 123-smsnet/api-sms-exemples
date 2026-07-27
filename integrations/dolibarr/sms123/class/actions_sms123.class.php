<?php
/* Hooks du module 123-SMS - licence MIT
 * Ajoute un bouton « Envoyer un SMS » sur les fiches tiers, contact,
 * devis, commande, facture et expedition.
 */

class ActionsSms123
{
	public $db;
	public $results = array();
	public $resprints;
	public $errors = array();

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
		global $user;

		if (!isModEnabled('sms123') || !$user->hasRight('sms123', 'envoyer')) {
			return 0;
		}

		$contextes = explode(':', empty($parameters['context']) ? '' : $parameters['context']);
		$supportes = array('thirdpartycard', 'contactcard', 'propalcard', 'ordercard', 'invoicecard', 'expeditioncard');
		if (!count(array_intersect($contextes, $supportes))) {
			return 0;
		}

		dol_include_once('/sms123/class/sms123api.class.php');

		$numero = $this->numero($object);
		$message = $this->messagePropose($object);

		$url = dol_buildpath('/sms123/sms123index.php', 1)
			.'?numero='.urlencode($numero)
			.'&message='.urlencode($message);

		if (empty($numero)) {
			print '<span class="butActionRefused classfortooltip" title="Aucun numero de telephone sur cette fiche">Envoyer un SMS</span>';
		} else {
			print '<a class="butAction" href="'.$url.'">Envoyer un SMS</a>';
		}

		return 0;
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
