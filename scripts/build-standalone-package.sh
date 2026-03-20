#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -lt 2 ] || [ "$#" -gt 3 ]; then
  echo "Usage: $0 <package-name> <artifact-name> [zip|tar.gz]" >&2
  exit 1
fi

package_name="$1"
artifact_name="$2"
archive_format="${3:-zip}"

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
package_dir="$root_dir/packages/$package_name"
core_dir="$root_dir/packages/core"
work_dir="$root_dir/var/build/standalone-$package_name"
dist_dir="$root_dir/var/build/dist"
work_packages_dir="$work_dir/packages"
work_package_dir="$work_packages_dir/$package_name"
artifact_dir="$work_dir/$artifact_name"

if [ ! -d "$package_dir" ]; then
  echo "Package directory not found: $package_dir" >&2
  exit 1
fi

rm -rf "$work_dir"
mkdir -p "$work_packages_dir" "$dist_dir"

cp -R "$core_dir" "$work_packages_dir/core"
cp -R "$package_dir" "$work_package_dir"

export COMPOSER_MIRROR_PATH_REPOS=1
composer update --working-dir="$work_package_dir" --no-dev --prefer-dist --no-interaction --no-progress

mkdir -p "$artifact_dir"
cp -LR "$work_package_dir"/. "$artifact_dir"/
rm -f "$artifact_dir/composer.lock"

case "$archive_format" in
  zip)
    if ! command -v zip >/dev/null 2>&1; then
      echo "zip command is required to build $artifact_name" >&2
      exit 1
    fi

    rm -f "$dist_dir/$artifact_name.zip"
    (
      cd "$work_dir"
      zip -qr "$dist_dir/$artifact_name.zip" "$artifact_name"
    )
    echo "$dist_dir/$artifact_name.zip"
    ;;
  tar.gz)
    rm -f "$dist_dir/$artifact_name.tar.gz"
    tar -czf "$dist_dir/$artifact_name.tar.gz" -C "$work_dir" "$artifact_name"
    echo "$dist_dir/$artifact_name.tar.gz"
    ;;
  *)
    echo "Unsupported archive format: $archive_format" >&2
    exit 1
    ;;
esac
