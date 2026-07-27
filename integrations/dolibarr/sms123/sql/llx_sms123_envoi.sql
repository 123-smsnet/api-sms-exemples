-- Historique des SMS envoyes via 123-SMS.net - licence MIT
-- Les colonnes apparues apres la version 2.1 (fk_soc, reference, statut,
-- date_ar, erreur_ar) sont creees ici pour les nouvelles installations et
-- ajoutees par modSms123::migrer() sur les bases existantes.
CREATE TABLE llx_sms123_envoi(
	rowid       integer AUTO_INCREMENT PRIMARY KEY,
	entity      integer DEFAULT 1 NOT NULL,
	datec       datetime NOT NULL,
	fk_user     integer NULL,
	fk_soc      integer NULL,
	numero      varchar(255) NOT NULL,
	message     text,
	code        varchar(32),
	methode     varchar(48),
	origine     varchar(64),
	reference   varchar(64) NULL,
	statut      varchar(16) NULL,
	date_ar     datetime NULL,
	erreur_ar   varchar(64) NULL
) ENGINE=innodb;
