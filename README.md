# GIT to WordPress Plugin Directory Release Script

WordPress plugins are often hosted on GitHub or other GIT repositories for development purposes but then pushed to the WordPress Plugin SVN infrastruture for release in the Plugin Directory.

This script automates the process with a simple bash shell script.

This script was originally based on [code](https://github.com/WP-API/WP-API/blob/develop/bin/release.sh) from the [WP-API](https://github.com/WP-API/WP-API) project.

## Installation

The basic script should work for simple plugins without alteration, follow these instructions to install it in your GIT repo for use in the release process:

1. Create a new directory named "bin" in the root of your GIT repo and copy "release.sh" to it.
2. If you already have a readme.txt file, copy it to the same "bin" directory and rename it to "readme.template", otherwise grab the sample template file from "Sample Templates" directory and edit it as required.
3. Edit your new readme.template file and the hard coded tag name in the "Stable Tag:" line with "{{TAG}}".  You can use this tag in multiple places in the file if you choose to.
4. Still in your readme.template file, make sure the changelog is the last section of your file and has 1 blank line after it.
5. If you have a CHANGES.md file, make sure it is in the same format as the sample in the "Sample Templates" directory and is in the root of your GIT repo, if you don't create one.
6. Double check to make sure your execute bit is set on the release.sh file.
7. Commit your changes to your GIT repo.

## Usage

You can run the script from either the GIT repo's root directory or from within the "bin" directory.

The script has two parameters:

1. Tag to release.
2. SVN user name to use.

To do a release, do the following:

1. Tag a release in your GIT repo.
2. Change in to the "bin" directory of the repo.
3. Run "./release.sh TAG UserName"

The script will do several things and then ask for confirmation to commit the changes to the SVN tree.

At this time you have the opportunity to verify what will be commit, the working copy will be in your system's temporary directory.

If everything is ok, you MUST type in "YES", all in capitals and then hit enter.

The script will then commit the changes to the SVN tree and you may be prompted for your SVN password (if you are it will happen twice, once for the commit and once for the tag).

## Advanced Items

The basic script assumes several things which may not be true for your install, things you should check for are:

- The script assumes your GIT check directory is the slug of your WordPress plugin.  You can override this by editing the script and changing the "PLUGIN=" line to the correct slug.
- The system command "mktemp" is used to store the temporary working copy of the plugin, you may want to change this if you have a busy system and don't want to look through a pile of temp directories to find the one you are looking for.
- By default the following files are not commited to the SVN repo: README.md, CHANGES.md and the "bin" directory.  If you have other files you wish to exclude, edit the script and go down to the "# Remove special files" section and add them to the list of files to remove.
- The commit messages are very basic, if you want to have something more meaningful to your project, edit the script at the "# Commit the changes" line and "# tag_ur_it" line.