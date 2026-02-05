#!/bin/bash
# Build production ZIP for DesignStudio Flow
# Usage: npm run release OR ./scripts/build-release.sh

set -e

# Get version from package.json
VERSION=$(node -p "require('./package.json').version")
PLUGIN_VERSION=$(node -e "const fs = require('fs'); const match = fs.readFileSync('designstudio-flow.php', 'utf8').match(/Version:\\s*([^\\n]+)/); console.log(match ? match[1].trim() : '')")

if [ -z "$PLUGIN_VERSION" ]; then
  echo "❌ Unable to read plugin version from designstudio-flow.php"
  exit 1
fi

if [ "$VERSION" != "$PLUGIN_VERSION" ]; then
  echo "❌ Version mismatch"
  echo "   package.json: $VERSION"
  echo "   designstudio-flow.php: $PLUGIN_VERSION"
  echo "   Update version numbers before building."
  exit 1
fi

echo "📦 Building DesignStudio Flow v$VERSION..."

# Build Vue assets
echo "🔨 Building Vue assets..."
echo "🧹 Cleaning previous Vite assets..."
rm -rf assets/.vite
rm -f assets/js/editor.js assets/js/frontend.js assets/js/*-*.js
rm -f assets/css/editor.css assets/css/FrontendApp.css
npm run build

if [ -f "assets/manifest.json" ]; then
  MANIFEST_PATH="assets/manifest.json"
elif [ -f "assets/.vite/manifest.json" ]; then
  MANIFEST_PATH="assets/.vite/manifest.json"
else
  echo "❌ Build failed: assets/manifest.json (or assets/.vite/manifest.json) not found"
  exit 1
fi

# Create build directory
rm -rf build
mkdir -p build/designstudio-flow

# Copy production files
echo "📁 Copying production files..."
rsync -a --exclude-from=".distignore" ./ build/designstudio-flow/

# Create ZIP
echo "🗜️  Creating ZIP archive..."
cd build
rm -f "../designstudio-flow-$VERSION.zip"
zip -rq "../designstudio-flow-$VERSION.zip" designstudio-flow
cd ..

# Cleanup
rm -rf build

echo ""
echo "✅ Created: designstudio-flow-$VERSION.zip"
echo ""
echo "To release:"
echo "  1. Commit your changes: git add . && git commit -m 'Release v$VERSION'"
echo "  2. Create a tag: git tag v$VERSION"
echo "  3. Push: git push origin main --tags"
