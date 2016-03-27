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

// We need to set some platform specific settings.
if( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
	$platform_null = ' > nul 2>&1';
	$platform = 'win';
} else {
	$platform_null = ' > /dev/null 2> 1';
	$platform = 'nix';
}

// If we have less than two parameters ( [0] is always the script name itself ), bail.
if( $argc < 3 ) {
	echo "Error, you must provide at least a path and tag!" . PHP_EOL;

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

echo "GIT repo path to use: $path" . PHP_EOL;

/* Check to see if we have a settings file, the order is:
 *
 * 1. Root of the GIT Repo.
 * 2. /release directory in the GIT Repo.
 * 3. /bin directory in the GIT Repo.
 * 4. Current directory.
 * 5. Release.ini in the current directory.
 */
$plugin_release_ini = false;
if( file_exists( $path . '/release.ini' ) ) {
	$plugin_release_ini = $path . '/release.ini';
} else if( file_exists( $path . '/release/release.ini' ) ) {
	$plugin_release_ini = $path . '/release/release.ini';
} else if( file_exists( $path . '/bin/release.ini' ) ) {
	$plugin_release_ini = $path . '/bin/release.ini';
}

$default_ini_settings = parse_ini_file( './release.ini' );
$local_ini_settings = $plugin_ini_settings = array();

if( file_exists( '../release.ini' ) ) {
	echo "Local release.ini to use: ../release.ini" . PHP_EOL;

	$local_ini_settings = parse_ini_file( '../release.ini' );
}

if( $plugin_release_ini != false ) {
	echo "Plugin release.ini to use: $plugin_release_ini" . PHP_EOL;

	$plugin_ini_settings = parse_ini_file( $plugin_release_ini );
}

// Merge the three settings arrays in to a single one.  We can't use array_merge() as
// we don't want a blank entry to override a setting in another file that has a value.
// For example svn-username may not be set in the default or plugin ini files but in
// the local file, but the "key" exists in all three.  The "blank" key in the plugin
// file would wipe out the value in the local file.
$ini_settings = $default_ini_settings;

foreach( $local_ini_settings as $key => $value ) {
	if( trim( $value ) != '' ) {
		$ini_settings[$key] = $value;
	}
}

foreach( $plugin_ini_settings as $key => $value ) {
	if( trim( $value ) != '' ) {
		$ini_settings[$key] = $value;
	}
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

if( ! empty( $config_settings['temp-dir'] ) && is_dir( $config_settings['temp-dir'] ) ) {
	$sys_temp_dir = $config_settings['temp-dir'];
} else {
	$sys_temp_dir = sys_get_temp_dir();
}

// Get a temporary working directory to checkout the SVN repo to.
$temp_dir = tempnam( $sys_temp_dir, "GWP" );
unlink( $temp_dir );
mkdir( $temp_dir );
echo "Temporary dir: $temp_dir" . PHP_EOL;

// Get a temporary filename for the GIT tar file we're going to checkout later.
$temp_file = tempnam( $sys_temp_dir, "GWP" );

// Ok, time to get serious, change to the GIT repo directory.
$home_dir = getcwd();
chdir( $path );

// Let's make sure the local repo is up to date, do a pull.
echo "Pulling the current repo..." . PHP_EOL;
exec( '"' . $config_settings['git-path'] . 'git" pull ' .  $platform_null, $output, $result );

// Let's make sure the tag exists.
echo "Checking if the tag exists in git...";
exec( '"' . $config_settings['git-path'] . 'git" rev-parse "' . $tag . '"' .  $platform_null, $output, $result );

if( $result ) {
	echo " no." . PHP_EOL;

	if( ! $config_settings['git-do-not-tag'] ) {
		echo "Aborting, tag not found in GIT and we're not tagging one!" . PHP_EOL;

		clean_up( $temp_dir, $temp_file, $platform );

		chdir( $home_dir );
		exit;
	} else {
		echo "Tagging " . $tag . " in the GIT repo...";

		exec( '"' . $config_settings['git-path'] . 'git" tag "' . $tag . '" -m "' . $config_settings['git-tag-message'] . '' .  $platform_null, $output, $result );

		if( $result ) {
			echo " error creating tag!" . PHP_EOL;

			clean_up( $temp_dir, $temp_file, $platform );

			chdir( $home_dir );
			exit;
		} else {
			echo " done." . PHP_EOL;
		}
	}
} else {
	echo " yes!" . PHP_EOL;
}

// Let's check to see if the tag already exists in SVN, if we're using a tag that is.
if( ! $config_settings['svn-do-not-tag'] ) {
	exec( '"' . $config_settings['svn-path'] . 'svn" info "' . $config_settings['svn-url'] . '/tags/' . $tag . '"' .  $platform_null, $output, $result );

	if( ! $result ) {
		echo "Error, tag already exists in SVN." . PHP_EOL;

		clean_up( $temp_dir, $temp_file, $platform );

		chdir( $home_dir );
		exit;
	}
}

// Time to checkout the SVN tree.
echo "Checking out SVN tree from: {$config_settings['svn-url']}/trunk" . PHP_EOL;
exec( '"' . $config_settings['svn-path'] . 'svn" co "' . $config_settings['svn-url'] . '/trunk" "' . $temp_dir . '"' .  $platform_null, $output, $result );

if( $result ) {
	echo "Error, SVN checkout failed." . PHP_EOL;

	clean_up( $temp_dir, $temp_file, $platform );

	chdir( $home_dir );
	exit;
}

// Extract the GIT repo files to the SVN checkout directory via a tar file.
echo "Extracting GIT repo for update...";
exec( '"' . $config_settings['git-path'] . 'git" archive --format="zip" "' . $tag . '" > "' . $temp_file . '"', $output, $result );

if( $result ) {
	echo "Error, GIT extract failed." . PHP_EOL;

	clean_up( $temp_dir, $temp_file, $platform );

	chdir( $home_dir );
	exit;
}

$zip = new ZipArchive;
if ( $zip->open( $temp_file, ZipArchive::CHECKCONS ) === TRUE ) {
	if( $zip->numFiles == 0 || FALSE === $zip->extractTo( $temp_dir ) ) {
		echo "Error, extracting zip files failed." . PHP_EOL;

		clean_up( $temp_dir, $temp_file, $platform );

		chdir( $home_dir );
		exit;
	}

	$zip->close();
} else {
	echo "Error, opening zip file failed." . PHP_EOL;

	clean_up( $temp_dir, $temp_file, $platform );

	chdir( $home_dir );
	exit;
}

echo " done!" . PHP_EOL;

// Get the readme and changelog files if they exist.
echo "Generating readme.txt...";
$readme = $changelog = false;

if( $config_settings['readme-template'] && file_exists( $path . '/' . $config_settings['readme-template'] ) ) {
	$readme = file_get_contents( $path . '/' . $config_settings['readme-template'] );

	// Replace any placeholders that are in the template file.
	$readme = release_replace_placeholders( $readme, $placeholders );
}

if( $config_settings['changelog'] && file_exists( $path . '/' . $config_settings['changelog'] ) ) {
	$changelog = file_get_contents( $path . '/' . $config_settings['changelog'] );

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

	fclose( $readme_file );
}

echo " done!" . PHP_EOL;

echo "Deleting files...";
// Get a list of files to delete.
$delete_files = explode( ',', $config_settings['DeleteFiles'] );
$prefix = ' ';

// Delete the files.
foreach( $delete_files as $file ) {
	$file = trim( $file );
	if( file_exists( $temp_dir . '/' . $file ) ) {
		unlink( $temp_dir . '/' . $file );
		echo $prefix . $file;
		$prefix = ', ';
	}
}

echo PHP_EOL;

echo "Deleting directories...";

// Get a list of directories to delete.
$delete_dirs = explode( ',', $config_settings['DeleteDirs'] );
$prefix = ' ';

// Delete the directories.
foreach( $delete_dirs as $dir ) {
	$dir = trim( $dir );
	if( is_dir( $temp_dir . '/' . $dir ) ) {
		delete_tree( $temp_dir . '/' . $dir );
		echo $prefix . $dir;
		$prefix = ', ';
	}
}

echo PHP_EOL;

// We need to move to the SVN temp directory to do some SVN commands now.
chdir( $temp_dir );

// Do an SVN status to get any files we need to add to the wordpress.org SVN tree.
echo "Files to add to SVN...";
exec( '"' . $config_settings['svn-path'] . 'svn" status >' .  $temp_file, $output, $result );

// Since we can't redirect to null in this case (we want the output) use the temporary file to hold the output and now read it in.
$output = file_get_contents( $temp_file );

// Let's convert the end of line marks in case we're on Windows.
$output = str_replace( "\r\n", "\n", $output );

// Now split the output in to lines.
$output = explode( "\n", $output );
$prefix = ' ';

$platform_null = '';

foreach( $output as $line ) {
	$first_char = substr( $line, 0, 1 );
	$name = trim( substr( $line, 1 ) );

	if( $first_char == '?' ) {
		exec( '"' . $config_settings['svn-path'] . 'svn" add "' . $name . '"' . $platform_null, $output, $result );

		echo $prefix . $name;
		$prefix = ', ';
	}
}

echo PHP_EOL;

// Compare the GIT and SVN directories to see if there are any files we need to delete.
echo "Files to delete from SVN...";
$git_files = get_file_list( $path );
$svn_files = get_file_list( $temp_dir );
$prefix = ' ';

foreach( $svn_files as $file ) {
	if( ! in_array( $file, $git_files ) && $file != '.svn' && $file != 'readme.txt' ) {
		exec( '"' . $config_settings['svn-path'] . 'svn" delete ' . $file . $platform_null, $output, $result );

		echo $prefix . $file;
		$prefix = ', ';
	}
}

echo PHP_EOL;

echo PHP_EOL;
echo "About to commit $tag. Double-check $temp_dir to make sure everything looks fine." . PHP_EOL;
echo "Type 'YES' in all capitals and then return to continue." . PHP_EOL;

$fh = fopen( 'php://stdin', 'r' );
$message = fgets( $fh, 1024 ); // read the special file to get the user input from keyboard
fclose( $fh );

if( trim( $message ) != 'YES' ) {
	echo "Commit aborted." . PHP_EOL;

	clean_up( $temp_dir, $temp_file, $platform );

	chdir( $home_dir );
	exit;
}

echo "Committing to SVN..." . PHP_EOL;
exec( '"' . $config_settings['svn-path'] . 'svn" commit -m "' . $config_settings['svn-commit-message'] . '"', $output, $result );

if( $result ) {
	echo "Error, commit failed." . PHP_EOL;

	clean_up( $temp_dir, $temp_file, $platform );

	chdir( $home_dir );
	exit;
}

if( ! $config_settings['svn-do-not-tag'] ) {
	echo "Tagging SVN..." . PHP_EOL;

	exec( '"' . $config_settings['svn-path'] . 'svn" copy "' . $config_settings['svn-url'] . '/trunk" "' . $config_settings['svn-url'] . '/tags/' . $tag . '" -m "' . $config_settings['svn-tag-message'] . '"', $output, $result );

	if( $result ) {
		echo "Error, tag failed." . PHP_EOL;

		clean_up( $temp_dir, $temp_file, $platform );

		chdir( $home_dir );
		exit;
	}
}

// Time to clean up.
clean_up( $temp_dir, $temp_file, $platform );

// Return home.
chdir( $home_dir );

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
