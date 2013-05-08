#!/bin/bash
dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
cd "$dir/.."
zip data.zip data -r --quiet --exclude data/cache/html\* data/cache/users/\*
