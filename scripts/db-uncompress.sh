#!/bin/bash
dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)"
cd "$dir/.."
unzip -q -o data.zip
