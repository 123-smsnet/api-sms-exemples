<?php
/**
 * Configuration globale du plugin 123-SMS pour Jeedom.
 *
 * Licence MIT — 123-SMS.net
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
	<fieldset>
		<legend><i class="fas fa-key"></i> {{Compte 123-SMS}}</legend>

		<div class="form-group">
			<label class="col-md-4 control-label">{{Identifiant du compte}}
				<sup><i class="fas fa-question-circle tooltips" title="{{Transmis par e-mail à l'inscription (espace client puis rubrique API)}}"></i></sup>
			</label>
			<div class="col-md-4">
				<input class="configKey form-control" data-l1key="identifiant" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-md-4 control-label">{{Clé API}}
				<sup><i class="fas fa-question-circle tooltips" title="{{Se génère dans l'espace client, rubrique API puis Générer clé API}}"></i></sup>
			</label>
			<div class="col-md-4">
				<input class="configKey form-control inputPassword" data-l1key="cle_api" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-md-4 control-label">{{Expéditeur (Sender-ID)}}
				<sup><i class="fas fa-question-circle tooltips" title="{{11 caractères maximum. Laissez vide pour un numéro court standard. Le destinataire ne peut pas répondre à un SMS portant un Sender-ID.}}"></i></sup>
			</label>
			<div class="col-md-4">
				<input class="configKey form-control" data-l1key="expediteur" maxlength="11" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-md-4 control-label">{{Envoi à blanc (mode test)}}
				<sup><i class="fas fa-question-circle tooltips" title="{{L'API répond comme pour un envoi réel (code 92) mais rien n'est envoyé ni débité}}"></i></sup>
			</label>
			<div class="col-md-4">
				<input type="checkbox" class="configKey" data-l1key="mode_test" />
			</div>
		</div>

		<div class="form-group">
			<label class="col-md-4 control-label"></label>
			<div class="col-md-6">
				<span class="label label-info" style="font-size:0.95em;white-space:normal;display:inline-block;text-align:left;padding:8px">
					{{Crédits prépayés, sans abonnement et sans date d'expiration. Inscription gratuite avec 5 SMS offerts pour tester :}}
					<a href="https://www.123-sms.net/" target="_blank" style="color:#fff;text-decoration:underline">123-sms.net</a>
				</span>
			</div>
		</div>
	</fieldset>
</form>
