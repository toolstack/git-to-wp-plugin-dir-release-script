<?php
# release.php
#
# Takes a tag to release, and syncs it to WordPress.org
#
# Notes:
#		- You must pass in a valid tag to the script as the first parameter
#		- You can pass an SVN user name (case sensitive) as the second
#		  parameter of the script if your SVN account is not the same as your
#         current user id.
#       - You may be prompted for your SVN password.
#		- By default the plugin name used for WordPress.org is the directory
#		  name, if this is not the case, change the "PLUGIN=" line below.
#		- If the tag already exists in SVN the script will exit.
#		- The script will handle both added and deleted files.
#		- If you use "trunk" for the tag name, the script will pull the
#         "master" branch from the GIT repo and push it to the trunk of
#		  the WordPress SVN repo and not create a new tag for it.

GLOBAL $argc, $argv;

// If we have less than two parameters ( [0] is always the script name itself ), bail.
if( $argc < 3 ) {
	echo "Error, you must provide at least a path and tag!\r\n";
	
	exit;
}

// First param is the path/slug to use, second is the tag.
$path = $argv[1];
$tag = $argv[2];

// Third (optional) is the svn user to use.
if( $argc > 3 ) {
	$svn_username = $argv[3];
}

$path_first_char = substr( $path, 0, 1 );
$path_second_char = substr( $path, 1, 1 );

// The path can either be an absolute path, a relative path or just a tag.  If it's just a tag, then we assume it's in the directory above us.
if( $path_first_char != '.' && $path_first_char != '/' && $path_first_char != '\\' && $path_second_char != ':' ) {
	$path = '../' . $path;
}

// Let's get the realpath.
$path = realpath( $path );

if( $path == false ) {
	echo "Error, path to GIT repo not found!";

	exit;
}

echo "GIT repo path to use: $path\r\n";

/* Check to see if we have a settings file, the order is:
 *
 * 1. Root of the GIT Repo.
 * 2. /release directory in the GIT Repo.
 * 3. /bin directory in the GIT Repo.
 * 4. Current directory.
 * 5. Default.ini in the current directory.
 */
$release_ini = false;
if( file_exists( $path . '/release.ini' ) ) {
	$release_ini = $path . '/release.ini';
} else if( file_exists( $path . '/release/release.ini' ) ) {
	$release_ini = $path . '/release/release.ini';	
} else if( file_exists( $path . '/bin/release.ini' ) ) {
	$release_ini = $path . '/bin/release.ini';	
} else if( file_exists( './release.ini' ) ) {
	$release_ini = './release.ini';	
}

if( $release_ini != false ) {
	echo "release.ini to use: $release_ini\r\n";
	
	$ini_settings = parse_ini_file( $release_ini );
} else {
	$ini_settings = parse_ini_file( 'default.ini' );
}

// The plugin slug is overridable in the ini file, so if it exists in the ini file use it, otherwise 
// assume the current basename of the path is the slug (after converting it to lower case and 
// replacing spaces with dashes.
if( $ini_settings['plugin-slug'] ) {
	$plugin_slug = $ini_settings['plugin-slug'];
} else {
	$plugin_slug = basename( $path );
	$plugin_slug = strtolower( $plugin_slug );
	$plugin_slug = str_replace( ' ', '-', $plugin_slug );
}

// Now that we have all the settings we can setup our replacement patterns.
$placeholders = array( 'tag' => $tag, 'TAG' => $tag, 'plugin-slug' => $plugin_slug );

// Now create our configuration settings by taking the ini settings and replacing any placeholders they may contain.
$config_settings = array();
foreach( $ini_settings as $setting => $value ) {
	$config_settings[$setting] = release_replace_placeholders( $value, $placeholders );
}

// We need to send some output to null, let's support both Unix and Windows.
$platform_null = ' > /dev/null 2> 1';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	$platform_null = ' > nul 2> 1';
}
$platform_null = '';

// Get a temporary working directory to checkout the SVN repo to.
$temp_dir = tempnam( sys_get_temp_dir(), "GWP" );
unlink( $temp_dir );
mkdir( $temp_dir );

// Get a temporary filename for the GIT tar file we're going to checkout later.
$zip_file = tempnam( sys_get_temp_dir(), "GWP" );

// Ok, time to get serious, change to the GIT repo directory.
$home_dir = getcwd();
chdir( $path );

// Let's make sure the tag exists.
exec( '"' . $config_settings['git-path'] . 'git" rev-parse "' . $tag . '"' .  $platform_null, $output, $result );

if( $result ) {
	echo "Error, tag not found in the GIT repo!\r\n";
	
	chdir( $home_dir );
	exit;
}

// Let's check to see if the tag already exists in SVN, if we're using a tag that is.
if( ! $config_settings['svn-do-not-tag'] ) {
	exec( '"' . $config_settings['svn-path'] . 'svn" info "' . $plugin_slug . '/tags/' . $tag . '"' .  $platform_null, $output, $result );
	
	if( $result ) {
		echo "Error, tag already exists in SVN.\r\n";
		
		chdir( $home_dir );
		exit;
	}
}

// Time to checkout the SVN tree.
exec( '"' . $config_settings['svn-path'] . 'svn" co "' . $config_settings['svn-url'] . '/trunk" "' . $temp_dir . '"' .  $platform_null, $output, $result );

if( $result ) {
	echo "Error, SVN checkout failed.\r\n";
	
	chdir( $home_dir );
	exit;
}
	
// Extract the GIT repo files to the SVN checkout directory via a tar file.	
exec( '"' . $config_settings['git-path'] . 'git" archive --format="zip" "' . $tag . '" > "' . $zip_file . '"' .  $platform_null, $output, $result );

if( $result ) {
	echo "Error, GIT extract failed.\r\n";
	
	chdir( $home_dir );
	exit;
}

$zip = new ZipArchive;
if ( $zip->open( $zip_file ) === TRUE ) {
	$zip->extractTo( $temp_dir );
	$zip->close();
} else {
	echo "Error, extracting zip file failed.\r\n";
	
	chdir( $home_dir );
	exit;
}

// Get the readme and changelog files if they exist.
$readme = $changelog = false;

if( $config_settings['readme-template'] && file_exists( $path . '/' . $config_settings['readme-template'] ) ) {
	$readme = readfile( $path . '/' . $config_settings['readme-template'] );
	
	// Replace any placeholders that are in the template file.
	$readme = release_replace_placeholders( $readme, $placeholders );
}

if( $config_settings['changelog'] && file_exists( $path . '/' . $config_settings['changelog'] ) ) {
	$changelog = readfile( $path . '/' . $config_settings['changelog'] );
	
	// Since the changelog is in "standard" MarkDown format, convert it to "WordPress" MarkDown format.
	$changelog = preg_replace( '/^##/m','=', $changelog );
}

// If we found a readme/changelog write it out as readme.txt in the temp directory.
if( $readme != false ) {
	$readme_file = fopen( $temp_dir . '/readme.txt', 'w' );
	fwrite( $readme_file, $readme );
	
	if( $changelog != false ) {
		fwrite( $readme_file, $changelog );
	} 
}

// Get a list of files to delete.
$delete_files = explode( ',', $config_settings['DeleteFiles'] );

// Delete the files.
foreach( $delete_files as $file ) {
	unlink( $temp_dir . '/' . $file );
}

// Get a list of directories to delete.
$delete_dirs = explode( ',', $config_settings['DeleteDirs'] );

// Delete the directories.
foreach( $delete_dirs as $dir ) {
	delTree( $temp_dir . '/' . $dir );
}












function delTree( $dir ) {
	$files = array_diff( scandir( $dir ), array( '.', '..' ) );
	
	foreach ( $files as $file ) {
		if( is_dir( "$dir/$file" ) ) {
			delTree("$dir/$file");
		} else {
			unlink("$dir/$file");
		}
	}

	return rmdir( $dir );
} 

function release_replace_placeholders( $string, $placeholders ) {
	foreach( $placeholders as $tag => $value ) {
		$string = preg_replace( '/{{' . $tag . '}}/', $value, $string );
	}
	
	return $string;
}