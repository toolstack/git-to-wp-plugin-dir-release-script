<?php
/* release.php
 *
 * Takes a tag to release, and syncs it to WordPress.org
 *
 * Notes:
 *		- You must pass a directory path to the GIT repo of your plugin as the first parameter
 *		- You must pass in a tag to the script as the second parameter
 *       - You may be prompted for your SVN password.
 *		- If the tag already exists in SVN the script will exit.
 *		- The script will handle both added and deleted files.
 */
 
include_once( 'class.release.php' );

$release_script = new release;

$release_script->process_args();
$release_script->get_config();
$release_script->set_temp_dir_and_file();
$release_script->validate_git_repo();
$release_script->validate_svn_repo();
$release_script->checkout_svn_repo();
$release_script->extract_git_repo();
$release_script->generate_readme();
$release_script->delete_files_and_directories();
$release_script->add_files_to_svn();
$release_script->delete_files_from_svn();
$release_script->confirm_commit();
$release_script->commit_svn_changes();

