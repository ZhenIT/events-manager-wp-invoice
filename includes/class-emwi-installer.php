<?php
if ( ! class_exists( 'EMWI_Installer' ) ) {
	class EMWI_Installer {
		static function install() {

		}

		static function upgrade() {
			$emwi  = EM_WI_Bridge_Loader::instance();
			$dir = $emwi->includes_dir . 'upgrades';
			$files = scandir( $dir );
			if ( ! $files ) {
				return;
			}
			foreach ( $files as $upgrade_file ) {
				$ver = basename( $upgrade_file, '.php' );
				if ( is_file( $dir . DIRECTORY_SEPARATOR . $upgrade_file ) &&
				     version_compare( get_option( 'emwi_version', 0 ), $ver, '<' ) &&
				     version_compare( $emwi->version, $ver, '>=' )
				) {
					include( $dir . DIRECTORY_SEPARATOR . $upgrade_file );
					update_option( 'emwi_version', $ver, true );
				}
			}
		}
	}
}