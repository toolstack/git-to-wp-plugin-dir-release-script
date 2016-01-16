# install.sh
#
# Installs the release script in to a git repo.
#
# Notes:
#		- The release script will be installed in the repo, committed and pushed.
# 		- The only parameter required is the path to the repo you wish to install in.

INPATH=$1
BINPATH="$INPATH/bin"

# Fail on any error
set -e

echo "Creating bin directory..."
mkdir "$BINPATH"

echo "Copying release.sh..."
cp release.sh "$BINPATH"
chmod +x "$BINPATH/release.sh"

cd "$BINPATH"

echo "Commiting release.sh to git..."
git add release.sh
git commit release.sh -m "Added relese script."

echo "Pushing commit to repo..."
git push 
