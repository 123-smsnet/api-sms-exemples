-- Choix « Rappel SMS » propre a une fiche d'evenement d'agenda - licence MIT
-- Une ligne n'existe QUE lorsque l'utilisateur s'ecarte du comportement
-- automatique (type d'evenement coche dans la configuration du module) :
--   actif = 1 : envoyer un rappel meme si le type n'est pas concerne
--   actif = 0 : ne pas envoyer de rappel pour cet evenement precis
CREATE TABLE llx_sms123_rdv(
	fk_actioncomm integer PRIMARY KEY,
	entity        integer DEFAULT 1 NOT NULL,
	actif         integer NOT NULL,
	datec         datetime NOT NULL
) ENGINE=innodb;
