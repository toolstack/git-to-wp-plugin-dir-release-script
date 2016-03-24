<?php
# functions.php
#
# Functions used in the release script.
#

function clean_up( $temp_dir, $temp_file, $platform ) {
	// We have to fudge the delete of the hidden SVN directory as unlink() will throw an error otherwise.
	if( $platform == 'win' ) {
		rename( $temp_dir . '/.svn/', $temp_dir . 'svn.tmp.delete' );
	}

	// Clean up the temporary dirs/files.
	delete_tree( $temp_dir );
	unlink( $temp_file );
}

function delete_tree( $dir ) {
	if( ! is_dir( $dir ) ) {
		return true;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		if( is_dir( "$dir/$file" ) ) {
			delete_tree("$dir/$file");
		} else {
			unlink("$dir/$file");
		}
	}

	return rmdir( $dir );
}

function get_file_list( $dir ) {
	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		if( is_dir( "$dir/$file" ) ) {
			array_merge( $files, get_file_list("$dir/$file") );
		}
	}

	return $files;
}

function release_replace_placeholders( $string, $placeholders ) {
	foreach( $placeholders as $tag => $value ) {
		$string = preg_replace( '/{{' . $tag . '}}/', $value, $string );
	}

	return $string;
}