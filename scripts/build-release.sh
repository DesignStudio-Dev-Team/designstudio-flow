#!/bin/bash
# Build production ZIP for DesignStudio Flow
# Usage: npm run release OR ./scripts/build-release.sh

set -e

# Get version from package.json
VERSION=$(node -p "require('./package.json').version")

echo "📦 Building DesignStudio Flow v$VERSION..."

# Build Vue assets
echo "🔨 Building Vue assets..."
npm run build

# Create build directory
rm -rf build
mkdir -p build/designstudio-flow

# Copy production files
echo "📁 Copying production files..."
cp -r assets build/designstudio-flow/
cp -r includes build/designstudio-flow/
cp -r templates build/designstudio-flow/
cp designstudio-flow.php build/designstudio-flow/
cp README.md build/designstudio-flow/

# Remove system files
echo "🧹 Cleaning up system files..."
find build/designstudio-flow -name ".DS_Store" -type f -delete
find build/designstudio-flow -name "Thumbs.db" -type f -delete

# Create ZIP
echo "🗜️  Creating ZIP archive..."
cd build
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
