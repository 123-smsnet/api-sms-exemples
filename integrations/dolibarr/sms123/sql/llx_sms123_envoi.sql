-- Historique des SMS envoyes via 123-SMS.net - licence MIT
CREATE TABLE llx_sms123_envoi(
	rowid       integer AUTO_INCREMENT PRIMARY KEY,
	entity      integer DEFAULT 1 NOT NULL,
	datec       datetime NOT NULL,
	fk_user     integer NULL,
	numero      varchar(255) NOT NULL,
	message     text,
	code        varchar(32),
	methode     varchar(48),
	origine     varchar(64)
) ENGINE=innodb;
