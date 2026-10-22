#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
PKG_NAME="quinrel"
ZIP_PATH="${DIST_DIR}/${PKG_NAME}.zip"
TMP_STAGE="$(mktemp -d /tmp/quinrel-build-XXXXXX)"

cleanup() {
    rm -rf "${TMP_STAGE}"
}
trap cleanup EXIT

echo "==> Packaging Quinrel Production Distribution..."

mkdir -p "${DIST_DIR}"
rm -f "${ZIP_PATH}"

TARGET_DIR="${TMP_STAGE}/${PKG_NAME}"
mkdir -p "${TARGET_DIR}"

# 1. Copy essential production files
cp "${ROOT_DIR}/quinrel.php" "${TARGET_DIR}/"
cp "${ROOT_DIR}/readme.txt" "${TARGET_DIR}/"
cp "${ROOT_DIR}/README.md" "${TARGET_DIR}/"
if [ -f "${ROOT_DIR}/license.txt" ]; then
    cp "${ROOT_DIR}/license.txt" "${TARGET_DIR}/"
elif [ -f "${ROOT_DIR}/LICENSE" ]; then
    cp "${ROOT_DIR}/LICENSE" "${TARGET_DIR}/"
fi
cp "${ROOT_DIR}/composer.json" "${TARGET_DIR}/"

# 2. Copy code and assets
cp -R "${ROOT_DIR}/src" "${TARGET_DIR}/"
cp -R "${ROOT_DIR}/build" "${TARGET_DIR}/"
if [ -d "${ROOT_DIR}/assets" ]; then
    cp -R "${ROOT_DIR}/assets" "${TARGET_DIR}/"
fi
if [ -d "${ROOT_DIR}/languages" ]; then
    cp -R "${ROOT_DIR}/languages" "${TARGET_DIR}/"
fi

# 3. Generate production autoloader without dev dependencies
echo "==> Generating production autoloader..."
(
    cd "${TARGET_DIR}"
    /Applications/XAMPP/xamppfiles/bin/php /usr/local/bin/composer dump-autoload --no-dev --optimize --quiet
)

# 4. Create ZIP
echo "==> Creating ZIP archive: ${ZIP_PATH}..."
(
    cd "${TMP_STAGE}"
    /usr/bin/zip -q -r "${ZIP_PATH}" "${PKG_NAME}" -x "*.DS_Store" "*__MACOSX*"
)

ZIP_SIZE=$(du -h "${ZIP_PATH}" | awk '{print $1}')
echo "==> SUCCESS: Created ${ZIP_PATH} (${ZIP_SIZE})"
echo "==> Ready to install via: WP Admin -> Plugins -> Add New -> Upload Plugin"
