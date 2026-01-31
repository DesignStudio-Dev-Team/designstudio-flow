---
description: How to create and deploy a new version of the plugin
---

Follow these steps to release a new version (e.g., `v1.1.0`).

### 1. Update Version Numbers
Update the version number in these two files:
- `package.json`: Update `"version": "1.1.0"`
- `designstudio-flow.php`: Update `Version: 1.1.0` and `define('DSF_VERSION', '1.1.0');`

### 2. Build Release Assets
Run this command to build the assets and create a production-ready ZIP:
```bash
npm run release
```
*Note: This creates a `designstudio-flow-1.1.0.zip` file locally, which you can verify.*

### 3. Commit Everything
Commit the version bump and the newly built assets (`assets/` folder).
```bash
git add .
git commit -m "Release v1.1.0"
```

### 4. Tag and Push
Create a git tag and push it to GitHub. This triggers the release action.
```bash
git tag v1.1.0
git push origin main --tags
```

### 5. Done!
- GitHub will automatically create a **Release** with the ZIP file attached.
- Review the release here: [https://github.com/DesignStudio-Dev-Team/designstudio-flow/releases](https://github.com/DesignStudio-Dev-Team/designstudio-flow/releases)
- Sites with the plugin installed will see an update notification.
