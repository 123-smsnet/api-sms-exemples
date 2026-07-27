<?php
/* Widget d'accueil du module 123-SMS - licence MIT
 * Affiche le solde de credits et les derniers SMS envoyes.
 * A activer dans Accueil > Configuration > Widgets.
 */
require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_sms123 extends ModeleBoxes
{
	public $boxcode = 'sms123';
	public $boximg = 'object_phoning';
	public $boxlabel;
	public $depends = array('sms123');

	public $db;
	public $param;
	public $enabled = 1;

	public $info_box_head = array();
	public $info_box_contents = array();

	/**
	 * @param DoliDB $db    base
	 * @param string $param parametres du widget
	 */
	public function __construct($db, $param = '')
	{
		global $langs;

		$langs->load('sms123@sms123');
		$this->db = $db;
		$this->param = $param;
		$this->boxlabel = $langs->trans('Sms123BoxTitle');
	}

	/**
	 * Charge le contenu du widget.
	 *
	 * @param int $max nombre de lignes d historique
	 * @return void
	 */
	public function loadBox($max = 5)
	{
		global $conf, $langs, $user;

		$this->max = $max;
		dol_include_once('/sms123/class/sms123api.class.php');

		$this->info_box_head = array('text' => $langs->trans('Sms123BoxTitle'));

		if (!$user->hasRight('sms123', 'envoyer')) {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="left opacitymedium"',
				'text' => $langs->trans('Sms123BoxNone'),
			);
			return;
		}

		$ligne = 0;

		// Solde (mis en cache 15 minutes : pas d appel API a chaque accueil)
		$solde = Sms123Api::soldeCache(900);
		$this->info_box_contents[$ligne][0] = array(
			'td' => 'class="left"',
			'text' => '<b>'.$langs->trans('Sms123Balance').'</b>',
		);
		$this->info_box_contents[$ligne][1] = array(
			'td' => 'class="right"',
			'text' => ($solde === null
				? '<span class="opacitymedium">'.$langs->trans('Sms123BalanceUnavailable').'</span>'
				: '<b style="color:'.($solde < 20 ? '#c83232' : '#268614').'">'
					.price2num($solde, 'MT').' '.$langs->trans('Sms123SmsUnit').'</b>'),
		);
		$ligne++;

		// Derniers envois
		$sql = 'SELECT datec, numero, code FROM '.MAIN_DB_PREFIX.'sms123_envoi';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' ORDER BY datec DESC';
		$sql .= $this->db->plimit($max, 0);
		$resql = $this->db->query($sql);

		if ($resql && $this->db->num_rows($resql) > 0) {
			while ($obj = $this->db->fetch_object($resql)) {
				$reussi = in_array($obj->code, array('80', '81'), true);
				$this->info_box_contents[$ligne][0] = array(
					'td' => 'class="left nowraponall"',
					'text' => dol_print_date($this->db->jdate($obj->datec), 'dayhour')
						.' &nbsp; '.dol_escape_htmltag($obj->numero),
				);
				$this->info_box_contents[$ligne][1] = array(
					'td' => 'class="right"',
					'text' => '<span style="color:'.($reussi ? '#268614' : '#c83232').'">'
						.dol_escape_htmltag($obj->code).'</span>',
				);
				$ligne++;
			}
			$this->db->free($resql);
		} else {
			$this->info_box_contents[$ligne][0] = array(
				'td' => 'class="left opacitymedium"',
				'text' => $langs->trans('Sms123BoxNone'),
			);
			$this->info_box_contents[$ligne][1] = array('td' => '', 'text' => '');
			$ligne++;
		}

		// Lien vers la page d envoi
		$this->info_box_contents[$ligne][0] = array(
			'td' => 'class="left"',
			'text' => '<a href="'.dol_buildpath('/sms123/sms123index.php', 1).'">'
				.$langs->trans('Sms123BoxSendOne').'</a>',
		);
		$this->info_box_contents[$ligne][1] = array(
			'td' => 'class="right"',
			'text' => '<a href="'.dol_buildpath('/sms123/sms123masse.php', 1).'">'
				.$langs->trans('Sms123MassLink').'</a>',
		);
	}

	/**
	 * Affiche le widget.
	 *
	 * @param array $head     entete
	 * @param array $contents contenu
	 * @param int   $nooutput 1 pour renvoyer la chaine au lieu de l afficher
	 * @return string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
