#!/bin/bash
dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
cd "$dir/.."
zip data.zip data -rq0 --exclude data/cache/html\*
