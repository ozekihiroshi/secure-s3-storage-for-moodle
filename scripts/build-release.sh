#!/bin/sh
set -eu

repositoryroot="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
cd "$repositoryroot"

output="${1:-build/tool_secure_s3_storage.zip}"
case "$output" in
    /*) ;;
    *) output="$repositoryroot/$output" ;;
esac

for command in docker git tar zip unzip sha256sum; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Required release command is unavailable: $command" >&2
        exit 1
    fi
done

if [ -n "$(git status --porcelain --untracked-files=all)" ]; then
    echo "Plugin repository must be clean before building a release ZIP." >&2
    exit 1
fi

outputdirectory="$(dirname "$output")"
mkdir -p "$outputdirectory"
outputdirectory="$(cd "$outputdirectory" && pwd)"
output="$outputdirectory/$(basename "$output")"

buildroot="$(mktemp -d)"
stagedplugin="$buildroot/secure_s3_storage"
temporaryzip="$buildroot/tool_secure_s3_storage.zip"
trap 'rm -rf "$buildroot"' EXIT HUP INT TERM
mkdir -p "$stagedplugin"

git archive --format=tar HEAD -- \
    . \
    ':(exclude).gitattributes' \
    ':(exclude).github' \
    ':(exclude).gitignore' \
    ':(exclude)scripts' |
    tar -xf - -C "$stagedplugin"

docker run --rm \
    --user "$(id -u):$(id -g)" \
    --env COMPOSER_HOME=/tmp/composer \
    --volume "$stagedplugin:/app" \
    --workdir /app \
    composer:2.8.12 \
    install \
        --no-dev \
        --classmap-authoritative \
        --no-interaction \
        --no-progress \
        --no-scripts

test -f "$stagedplugin/vendor/autoload.php"
test -f "$stagedplugin/vendor/aws/aws-sdk-php/LICENSE"

cp "$stagedplugin/readme_moodle.txt" "$stagedplugin/vendor/readme_moodle.txt"
test -f "$stagedplugin/vendor/readme_moodle.txt"
(
    cd "$buildroot"
    zip -qr "$temporaryzip" secure_s3_storage
)

unzip -Z1 "$temporaryzip" | awk '
    $0 !~ /^secure_s3_storage\// { invalid = 1 }
    $0 == "secure_s3_storage/version.php" { version = 1 }
    $0 == "secure_s3_storage/settings.php" { settings = 1 }
    $0 == "secure_s3_storage/thirdpartylibs.xml" { thirdpartylibs = 1 }
    $0 == "secure_s3_storage/vendor/autoload.php" { autoload = 1 }
    $0 == "secure_s3_storage/vendor/readme_moodle.txt" { readme = 1 }
    $0 ~ /^secure_s3_storage\/\.github\// { invalid = 1 }
    $0 ~ /^secure_s3_storage\/scripts\// { invalid = 1 }
    END {
        exit invalid || !version || !settings || !thirdpartylibs || !autoload || !readme
    }
'

mv "$temporaryzip" "$output"
trap - EXIT HUP INT TERM
rm -rf "$buildroot"

echo "Plugin commit: $(git rev-parse HEAD)"
sha256sum "$output"
