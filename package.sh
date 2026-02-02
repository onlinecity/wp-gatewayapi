#!/bin/bash

# Exit on error
set -e

# Get the directory of the script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# 1. Extract version from gatewayapi.php
VERSION=$(grep -m 1 "Version:" gatewayapi.php | awk '{print $NF}' | tr -d '\r')

if [ -z "$VERSION" ]; then
    echo "Error: Could not find version in gatewayapi.php"
    exit 1
fi

echo "Packaging GatewayAPI version $VERSION..."

# 2. Build admin-ui
echo "Building admin-ui..."
cd admin-ui
npm install
npm run build
cd ..

# 3. Create a temporary packaging directory
TEMP_DIR="temp_package"
PACKAGE_NAME="gatewayapi"
ZIP_FILE="gatewayapi-$VERSION.zip"
BUILD_DIR="build"

rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR/$PACKAGE_NAME"

# 4. Copy files to the package directory
echo "Preparing files..."
# Copy everything from root except some files/folders
rsync -av --progress . "$TEMP_DIR/$PACKAGE_NAME" \
    --exclude "admin-ui" \
    --exclude "$TEMP_DIR" \
    --exclude "*.zip" \
    --exclude ".git" \
    --exclude ".DS_Store" \
    --exclude "package.sh" \
    --exclude "node_modules" \
    --exclude ".idea" \
    --exclude ".vscode"

# Copy only the dist folder from admin-ui
mkdir -p "$TEMP_DIR/$PACKAGE_NAME/admin-ui"
cp -r admin-ui/dist "$TEMP_DIR/$PACKAGE_NAME/admin-ui/"

# 5. Create the zip
echo "Creating zip file $ZIP_FILE..."
cd "$TEMP_DIR"
zip -r "../$ZIP_FILE" .
cd ..

# 6. Also prepare persistent build directory for CI/SVN deploy (contents only, not the zip)
echo "Preparing persistent build directory..."
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PACKAGE_NAME"
rsync -a "$TEMP_DIR/$PACKAGE_NAME/" "$BUILD_DIR/$PACKAGE_NAME/"

# 7. Cleanup temp directory
echo "Cleaning up..."
rm -rf "$TEMP_DIR"

echo "Done! Package created: $ZIP_FILE and build prepared in $BUILD_DIR/$PACKAGE_NAME"
