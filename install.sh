# install.sh
#
# Installs the release script in to a git repo.
#
# Notes:
#		- The release script will be installed in the repo, but not committed or pushed.
# 		- The only parameter required is the path to the repo you wish to install in.

INPATH=$1
BINPATH="$INPATH\bin"

# Fail on any error
set -e

mkdir "$BINPATH"

cp release.sh "$BINPATH"
chmod +x "$BINPATH\release.sh"