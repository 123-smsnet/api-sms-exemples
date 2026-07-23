<?php
// Nettoyage a la desinstallation : suppression des reglages du plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
delete_option( 'sms123_wc_options' );
