# GIT to WordPress Plugin Directory Release Script

WordPress plugins are often hosted on GitHub or other GIT repositories for development purposes but then pushed to the WordPress Plugin SVN infrastructure for release in the Plugin Directory.

This script automates the process with a php script intended to be run from a Unix shell or Windows command prompt.

This script was originally based on [code](https://github.com/WP-API/WP-API/blob/develop/bin/release.sh) from the [WP-API](https://github.com/WP-API/WP-API) project.

## Installation

The basic script should work for simple plugins without alteration.  The script is intended to be run from outside of your plugin repo and so requires very few changes to your current repo to work.

In it's simplest form you can do the following steps:

1. Clone your plugin GIT repo to your local system, let's say to /source/plugin-slug, for example.
2. Clone the release script to /source/git-to-wp-plugin-dir-release-script.
3. Start a shell and go to /source/git-to-wp-plugin-dir-release-script.
4. Run `php plugin-slug tag`, where tag is the release tag you want to push to wordpress.org.

## Usage

The script is intended to be run from it's own repo directory, you do not have to add it to your plugin's repo.

The script has two parameters:

1. Plugin slug/path.
2. Tag to release.

To do a release, do the following:

1. Tag a release in your GIT repo.
2. Change in to the "git-to-wp-plugin-dir-release-script" directory.
3. Run "php release.php plugin-slug TAG" (where TAG is usually something like 1.2 or 4.5 etc.)

The script will do several things and then ask for confirmation to commit the changes to the SVN tree.

At this time you have the opportunity to verify what will be commit, the working copy will be in your system's temporary directory.

If everything is ok, you MUST type in "YES", all in capitals and then hit enter.

The script will then commit the changes to the SVN tree and you may be prompted for your SVN password (possibly twice, once for the commit and once for the tag).

## Configuration

For the script to work, you must have three things accessible on your system's shell:

1. PHP
2. GIT
3. SVN

Ideally, these should be available in your path, however only PHP has that requirement, you can configured a path for both GIT and SVN.

The script uses a "release.ini" file to store several configuration variables to use, which will be explained shortly.

The script actually uses several release.ini files, to form it's configuration to allow for a mix of defaults, site specific and project specific overrides.

Three release.ini files are looked for in the following directories:

1. in the git-to-wp-plugin-dir-release-script repo directory (the defaults)
2. in the parent directory of git-to-wp-plugin-dir-release-script (the local site settings)
3. in the plugin repo's directory (it can be in either the bin, release or root directory of the repo)

These ini files are loaded in order, so settings from the plugin ini files will override the local or default settings (with the exception of blank settings, which will be ignored).

This lets you configure your release setup with a great deal of flexibility, while committing to your plugin directory the general settings required for anyone to perform the release.

Each of the release.ini files have the following format:

```
[General]
plugin-slug=
temp-dir=
readme-template=
changelog=

[SVN]
svn-url=https://plugins.svn.wordpress.org/{{plugin-slug}}
svn-username=
svn-do-not-tag=
svn-path=
svn-tag-message=Tagged v{{tag}}.
svn-commit-message=Updates for v{{tag}} release.

[GIT]
git-use-tag={{tag}}
git-path=
git-do-not-tag=true
git-tag-message=Tagged v{{tag}}.

[Delete]
DeleteFiles=
DeleteDirs=
```

### General Settings
This section contains the following directives:

* plugin-slug: The slug to use for this plugin, by default it is automatically generated from the directory name to which the GIT repo is checked out to.
* temp-dir: The temporary directory to use, by default the system temp directory.
* readme-template: The relative (to the plugin GIT repo) path/name of the readme.template file to use.
* changelog: The relative (to the plugin GIT repo) path/name of the CHANGLOG.md to use.

### SVN Settings
This section contains the following directives:

* svn-url: The full URI of your plugin's SVN repo.
* svn-username: The user name to use when committing changes to the SVN tree.
* svn-do-not-tag: Disable tagging of the release in the SVN tree.
* svn-path: Local path to the SVN utilities.
* svn-tag-message: The commit message when tagging the release in the SVN tree.
* svn-commit-message: The commit message when committing the changes to the trunk of the SVN tree.

### SVN Settings
This section contains the following directives:

* git-use-tag: The tag to use from the GIT repo, this can be a placeholder or a specific tag (like "master")
* git-path: Local path the GIT utilities.
* git-do-not-tag: By default the release script will check to see if the tag exists in the GIT repo and create it if it doesn't, setting this will instead abort the script if it is not found.
* git-tag-message: The commit message when committing the changes to the trunk of the SVN tree.

### Delete Settings
This section contains the following directives:

* DeleteFiles: A comma separated list of files to delete.
* DeleteDirs: A comma separated list of directories to delete

## Configuration Examples

Ok, so you've checked out the script and now you want to configure your plugin to use it, here's one way you might set it up:

	+---GIT to WP Plugin Dir Release Script
	|   |   CHANGES.md
	|   |   README.md
	|   |   release.ini							[1]
	|   |   release.php
	|   |
	|   \---Sample Templates
	|           CHANGES.md.sample
	|           readme.template.sample
	|           
	+---GP Additional Links
	|   |   CHANGES.md
	|   |   GlotPress-Logo-20px.png
	|   |   gp-additional-links.php
	|   |   README.md
	|   |   
	|   \---bin
	|           readme.template
	|   		release.ini						[3]
	|            
	+---release.ini								[2]

The first [1] releaes.ini is the default one you checked out with the release script, no changes are needed here.

The second [2] is in the parent directory contains a release.ini script with the following lines:

```
[SVN]
svn-username=MyUserName
svn-path=C:\Program Files\TortoiseSVN\bin\
```

These two lines let you set the path to the SVN utilities and what username you will be using for the SVN commits.

The third [3] ini is in your plugin repo and will contain something like:

```
[General]
readme-template=bin/readme.template

[SVN]
svn-do-not-tag=true

[Delete]
DeleteFiles=README.md, CHANGES.md
DeleteDirs=bin
```

This will set the plugin specific items you need.

 

