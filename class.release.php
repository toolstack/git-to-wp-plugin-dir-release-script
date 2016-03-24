<?php
# class.release.php
#
# The main release class used in the release script.
#

class release {
	private $platform_null;
	private $platform;
	private $line_ending;
	private $path;
	private $tag;
	private $svn_username;
	private $placeholders;
	private $config_settings;
	private $plugin_slug;
	private $sys_temp_dir;
	private $home_dir;
	private $temp_dir;

	public function __construct() {
		$this->home_dir = getcwd();

		// We need to set some platform specific settings.
		if( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
			$this->platform_null = ' > nul 2>&1';
			$this->platform = 'win';
			$this->line_ending = "\r\n";
		} else {
			$this->platform_null = ' > /dev/null 2> 1';
			$this->platform = 'nix';
			$this->line_ending = "\n";
		}
	}

	/*
	 *
	 * Private functions
	 *
	 */
	public function process_args() {
		GLOBAL $argc, $argv;

		// If we have less than two parameters ( [0] is always the script name itself ), bail.
		if( $argc < 3 ) {
			echo "Error, you must provide at least a path and tag!" . $this->line_ending;

			exit;
		}

		// First param is the path/slug to use, second is the tag.
		$this->path = $argv[1];
		$this->tag = $argv[2];

		// Third (optional) is the svn user to use.
		if( $argc > 3 ) {
			$this->svn_username = $argv[3];
		}

		$path_first_char = substr( $path, 0, 1 );
		$path_second_char = substr( $path, 1, 1 );

		// The path can either be an absolute path, a relative path or just a tag.  If it's just a tag, then we assume it's in the directory above us.
		if( $path_first_char != '.' && $path_first_char != '/' && $path_first_char != '\\' && $path_second_char != ':' ) {
			$this->path = '../' . $path;
		}

		// Let's get the realpath.
		$this->path = realpath( $this->path );

		if( $this->path == false ) {
			echo "Error, path to GIT repo not found!";

			exit;
		}
	}

	public function get_config() {
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
			echo "Local release.ini to use: ../release.ini" . $this->line_ending;

			$local_ini_settings = parse_ini_file( '../release.ini' );
		}

		if( $plugin_release_ini != false ) {
			echo "Plugin release.ini to use: $plugin_release_ini" . $this->line_ending;

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
			$this->plugin_slug = $ini_settings['plugin-slug'];
		} else {
			$this->plugin_slug = basename( $path );
			$this->plugin_slug = strtolower( $plugin_slug );
			$this->plugin_slug = str_replace( ' ', '-', $plugin_slug );
		}

		// Now that we have our config variables we can define the placeholders.
		$this->placeholders = array( 'tag' => $this->tag, 'TAG' => $this->tag, 'plugin-slug' => $this->plugin_slug );

		// Now create our configuration settings by taking the ini settings and replacing any placeholders they may contain.
		$config_settings = array();
		foreach( $ini_settings as $setting => $value ) {
			$this->config_settings[$setting] = $this->release_replace_placeholders( $value, $this->placeholders );
		}

		if( ! empty( $config_settings['temp-dir'] ) && is_dir( $config_settings['temp-dir'] ) ) {
			$this->sys_temp_dir = $config_settings['temp-dir'];
		} else {
			$this->sys_temp_dir = sys_get_temp_dir();
		}
	}

	public function set_temp_dir_and_file() {
		// Get a temporary working directory to checkout the SVN repo to.
		$this->temp_dir = tempnam( $this->sys_temp_dir, "GWP" );
		unlink( $this->temp_dir );
		mkdir( $this->temp_dir );
		echo "Temporary dir: {$this->temp_dir}" . $this->line_ending;

		// Get a temporary filename for the GIT tar file we're going to checkout later.
		$this->temp_file = tempnam( $this->sys_temp_dir, "GWP" );
	}

	public function validate_git_repo() {
		// Ok, time to get serious, change to the GIT repo directory.
		chdir( $this->path );

		// Let's make sure the local repo is up to date, do a pull.
		echo "Pulling the current repo..." . $this->line_ending;
		exec( '"' . $this->config_settings['git-path'] . 'git" pull ' .  $this->platform_null, $output, $result );

		// Let's make sure the tag exists.
		echo "Checking if the tag exists in git...";
		exec( '"' . $this->config_settings['git-path'] . 'git" rev-parse "' . $this->tag . '"' .  $this->platform_null, $output, $result );

		if( $result ) {
			echo " no." . $this->line_ending;

			if( ! $this->config_settings['git-do-not-tag'] ) {
				echo "Aborting, tag not found in GIT and we're not tagging one!" . $this->line_ending;

				$this->clean_up();

				exit;
			} else {
				echo "Tagging " . $this->tag . " in the GIT repo...";

				exec( '"' . $this->config_settings['git-path'] . 'git" tag "' . $this->tag . '" -m "' . $this->config_settings['git-tag-message'] . '' .  $this->platform_null, $output, $result );

				if( $result ) {
					echo " error creating tag!" . $this->line_ending;

					$this->clean_up();

					exit;
				} else {
					echo " done." . $this->line_ending;
				}
			}
		} else {
			echo " yes!" . $this->line_ending;
		}
	}

	public function validate_svn_repo() {
		// Let's check to see if the tag already exists in SVN, if we're using a tag that is.
		if( ! $this->config_settings['svn-do-not-tag'] ) {
			exec( '"' . $this->config_settings['svn-path'] . 'svn" info "' . $this->config_settings['svn-url'] . '/tags/' . $this->tag . '"' .  $this->platform_null, $output, $result );

			if( ! $result ) {
				echo "Error, tag already exists in SVN." . $this->line_ending;

				$this->clean_up();

				exit;
			}
		}
	}

	public function checkout_svn_repo() {
		// Time to checkout the SVN tree.
		echo "Checking out SVN tree from: {$this->config_settings['svn-url']}/trunk" . $this->line_ending;
		exec( '"' . $this->config_settings['svn-path'] . 'svn" co "' . $this->config_settings['svn-url'] . '/trunk" "' . $this->temp_dir . '"' .  $this->platform_null, $output, $result );

		if( $result ) {
			echo "Error, SVN checkout failed." . $this->line_ending;

			$this->clean_up();

			exit;
		}
	}

	public function extract_git_repo() {
		// Extract the GIT repo files to the SVN checkout directory via a tar file.
		echo "Extracting GIT repo for update...";
		exec( '"' . $this->config_settings['git-path'] . 'git" archive --format="zip" "' . $this->tag . '" > "' . $this->temp_file . '"', $output, $result );

		if( $result ) {
			echo "Error, GIT extract failed." . $this->line_ending;

			$this->clean_up();

			exit;
		}

		$zip = new ZipArchive;
		if ( $zip->open( $this->temp_file, ZipArchive::CHECKCONS ) === TRUE ) {
			if( $zip->numFiles == 0 || FALSE === $zip->extractTo( $this->temp_dir ) ) {
				echo "Error, extracting zip files failed." . $this->line_ending;

				$this->clean_up();

				exit;
			}

			$zip->close();
		} else {
			echo "Error, opening zip file failed." . $this->line_ending;

			$this->clean_up();

			exit;
		}

		echo " done!" . $this->line_ending;
	}

	public function generate_readme() {
		// Get the readme and changelog files if they exist.
		echo "Generating readme.txt...";
		$readme = $changelog = false;

		if( $this->config_settings['readme-template'] && file_exists( $this->path . '/' . $this->config_settings['readme-template'] ) ) {
			$readme = file_get_contents( $this->path . '/' . $this->config_settings['readme-template'] );

			// Replace any placeholders that are in the template file.
			$readme = $this->release_replace_placeholders( $readme, $this->placeholders );
		}

		if( $this->config_settings['changelog'] && file_exists( $this->path . '/' . $this->config_settings['changelog'] ) ) {
			$changelog = file_get_contents( $path . '/' . $this->config_settings['changelog'] );

			// Since the changelog is in "standard" MarkDown format, convert it to "WordPress" MarkDown format.
			$changelog = preg_replace( '/^##/m','=', $changelog );
		}

		// If we found a readme/changelog write it out as readme.txt in the temp directory.
		if( $readme != false ) {
			$readme_file = fopen( $this->temp_dir . '/readme.txt', 'w' );
			fwrite( $readme_file, $readme );

			if( $changelog != false ) {
				fwrite( $readme_file, $changelog );
			}

			fclose( $readme_file );
		}

		echo " done!" . $this->line_ending;
	}

	public function delete_files_and_directories() {
		echo "Deleting files...";
		// Get a list of files to delete.
		$delete_files = explode( ',', $this->config_settings['DeleteFiles'] );
		$prefix = ' ';

		// Delete the files.
		foreach( $delete_files as $file ) {
			$file = trim( $file );
			if( file_exists( $this->temp_dir . '/' . $file ) ) {
				unlink( $this->temp_dir . '/' . $file );
				echo $prefix . $file;
				$prefix = ', ';
			}
		}

		echo $this->line_ending;

		echo "Deleting directories...";

		// Get a list of directories to delete.
		$delete_dirs = explode( ',', $this->config_settings['DeleteDirs'] );
		$prefix = ' ';

		// Delete the directories.
		foreach( $delete_dirs as $dir ) {
			$dir = trim( $dir );
			if( is_dir( $this->temp_dir . '/' . $dir ) ) {
				$this->delete_tree( $this->temp_dir . '/' . $dir );
				echo $prefix . $dir;
				$prefix = ', ';
			}
		}

		echo $this->line_ending;
	}

	public function add_files_to_svn() {
		// We need to move to the SVN temp directory to do some SVN commands now.
		chdir( $this->temp_dir );

		// Do an SVN status to get any files we need to add to the wordpress.org SVN tree.
		echo "Files to add to SVN...";
		exec( '"' . $this->config_settings['svn-path'] . 'svn" status >' .  $this->temp_file, $output, $result );

		// Since we can't redirect to null in this case (we want the output) use the temporary file to hold the output and now read it in.
		$output = file_get_contents( $this->temp_file );

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
				exec( '"' . $this->config_settings['svn-path'] . 'svn" add "' . $name . '"' . $this->platform_null, $output, $result );

				echo $prefix . $name;
				$prefix = ', ';
			}
		}

		echo $this->line_ending;
	}

	public function delete_files_from_svn() {
		// Compare the GIT and SVN directories to see if there are any files we need to delete.
		echo "Files to delete from SVN...";
		$git_files = $this->get_file_list( $this->path );
		$svn_files = $this->get_file_list( $this->temp_dir );
		$prefix = ' ';

		foreach( $svn_files as $file ) {
			if( ! in_array( $file, $git_files ) && $file != '.svn' && $file != 'readme.txt' ) {
				exec( '"' . $this->config_settings['svn-path'] . 'svn" delete ' . $file . $this->platform_null, $output, $result );

				echo $prefix . $file;
				$prefix = ', ';
			}
		}

		echo $this->line_ending;
	}

	public function confirm_commit() {
		echo $this->line_ending;
		echo "About to commit {$this->tag}. Double-check {$this->temp_dir} to make sure everything looks fine." . $this->line_ending;
		echo "Type 'YES' in all capitals and then return to continue." . $this->line_ending;

		$fh = fopen( 'php://stdin', 'r' );
		$message = fgets( $fh, 1024 ); // read the special file to get the user input from keyboard
		fclose( $fh );

		if( trim( $message ) != 'YES' ) {
			echo "Commit aborted." . $this->line_ending;

			$this->clean_up();

			exit;
		}
	}

	public function commit_svn_changes() {
		echo "Committing to SVN..." . $this->line_ending;
		exec( '"' . $this->config_settings['svn-path'] . 'svn" commit -m "' . $this->config_settings['svn-commit-message'] . '"', $output, $result );

		if( $result ) {
			echo "Error, commit failed." . $this->line_ending;

			$this->clean_up();

			exit;
		}

		if( ! $this->config_settings['svn-do-not-tag'] ) {
			echo "Tagging SVN..." . $this->line_ending;

			exec( '"' . $this->config_settings['svn-path'] . 'svn" copy "' . $this->config_settings['svn-url'] . '/trunk" "' . $this->config_settings['svn-url'] . '/tags/' . $this->tag . '" -m "' . $this->config_settings['svn-tag-message'] . '"', $output, $result );

			if( $result ) {
				echo "Error, tag failed." . $this->line_ending;

				$this->clean_up();

				exit;
			}
		}

		$this->clean_up();
	}

	/*
	 *
	 * Private functions
	 *
	 */

	private function clean_up() {
		// We have to fudge the delete of the hidden SVN directory as unlink() will throw an error otherwise.
		if( $this->platform == 'win' ) {
			rename( $this->temp_dir . '/.svn/', $this->temp_dir . 'svn.tmp.delete' );
		}

		// Clean up the temporary dirs/files.
		$this->delete_tree( $this->temp_dir );
		unlink( $this->temp_file );

		chdir( $home_dir );
	}

	private function delete_tree( $dir ) {
		if( ! is_dir( $dir ) ) {
			return true;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			if( is_dir( "$dir/$file" ) ) {
				$this->delete_tree("$dir/$file");
			} else {
				unlink("$dir/$file");
			}
		}

		return rmdir( $dir );
	}

	private function get_file_list( $dir ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			if( is_dir( "$dir/$file" ) ) {
				array_merge( $files, $this->get_file_list("$dir/$file") );
			}
		}

		return $files;
	}

	private function release_replace_placeholders( $string, $placeholders ) {
		if( ! is_array( $placeholders ) ) {
			return $string;
		}
		
		foreach( $placeholders as $tag => $value ) {
			$string = preg_replace( '/{{' . $tag . '}}/', $value, $string );
		}

		return $string;
	}

}